<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * @group Sales
 * Simple POS style sales where stock is reduced upon transaction.
 */
class SaleController extends Controller
{
    /**
     * List Sales
     * 
     * Get paginated list of sales with filters.
     * 
     * @queryParam limit integer optional Items per page. Default 10.
     * @queryParam status string optional active / inactive.
     * @queryParam from_date date optional Filter by sale date (from).
     * @queryParam to_date date optional Filter by sale date (to).
     * @queryParam invoice_number string optional Search by invoice number.
     * @queryParam search string optional Search by invoice or product name.
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $limit = $request->query('limit', 10);

        $query = Sale::with(['items.product']);

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('sale_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('sale_date', '<=', $request->to_date);
        }

        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('items.product', function ($pq) use ($searchTerm) {
                      $pq->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        $sales = $query->orderBy('sale_date', 'desc')->paginate($limit);

        return response()->json([
            'message' => 'Sales fetched successfully',
            'data' => $sales->items(),
            'pagination' => [
                'total' => $sales->total(),
                'per_page' => $sales->perPage(),
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
            ]
        ]);
    }


    /**
     * Create Sale
     * 
     * Record a new sale and reduce stock.
     * 
     * @bodyParam invoice_number string required
     * @bodyParam sale_date date required
     * @bodyParam items array required
     * @bodyParam items.*.product_id string required
     * @bodyParam items.*.warehouse_id string required
     * @bodyParam items.*.quantity number required
     * @bodyParam items.*.unit_price number required
     */
    public function store(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|string|unique:sales,invoice_number',
            'sale_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $total = 0;
            foreach ($request->items as $item) {
                $total += $item['quantity'] * $item['unit_price'];
            }

            $sale = Sale::create([
                'invoice_number' => $request->invoice_number,
                'sale_date' => $request->sale_date,
                'total_amount' => $total,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];

                // Reduce stock
                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $item['warehouse_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: " . ($stock->product->name ?? $item['product_id']));
                }

                $stock->quantity -= $item['quantity'];
                $stock->save();

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $itemTotal,
                ]);
            }

            return response()->json([
                'message' => 'Sale recorded successfully',
                'data' => $sale->load('items.product')
            ], 201);
        });
    }

    /**
     * Get Sale
     * 
     * Show single sale details.
     * 
     * @urlParam id string required Sale UUID
     */
    public function show($id)
    {
        $sale = Sale::with(['items.product', 'items.warehouse'])->findOrFail($id);

        return response()->json([
            'message' => 'Sale retrieved successfully',
            'data' => $sale
        ]);
    }

    /**
     * Update Sale
     * 
     * Partially update sale or replace items and adjust stock.
     */
    public function update(Request $request, $id)
    {
        $sale = Sale::with('items')->findOrFail($id);

        $request->validate([
            'invoice_number' => 'sometimes|required|string|unique:sales,invoice_number,' . $id,
            'sale_date' => 'sometimes|required|date',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $sale) {
            $updateData = $request->only(['invoice_number', 'sale_date', 'notes']);

            if ($request->has('items')) {
                // 1. Revert old stock changes
                foreach ($sale->items as $oldItem) {
                    $stock = Stock::where('product_id', $oldItem->product_id)
                        ->where('warehouse_id', $oldItem->warehouse_id)
                        ->first();

                    if ($stock) {
                        $stock->quantity += $oldItem->quantity;
                        $stock->save();
                    }
                }

                // 2. Delete existing items
                $sale->items()->delete();

                // 3. Calculate new total and apply changes
                $total = 0;
                foreach ($request->items as $item) {
                    $total += $item['quantity'] * $item['unit_price'];
                }
                $updateData['total_amount'] = $total;
                $sale->update($updateData);

                // 4. Create new items and reduce stock
                foreach ($request->items as $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];

                    $stock = Stock::where('product_id', $item['product_id'])
                        ->where('warehouse_id', $item['warehouse_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$stock || $stock->quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product in update: " . ($stock->product->name ?? $item['product_id']));
                    }

                    $stock->quantity -= $item['quantity'];
                    $stock->save();

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $itemTotal,
                    ]);
                }
            } else {
                $sale->update($updateData);
            }

            return response()->json([
                'message' => 'Sale updated successfully',
                'data' => $sale->load('items.product')
            ]);
        });
    }

    /**
     * Delete Sale
     * 
     * Revert stock and soft delete.
     */
    public function destroy($id)
    {
        $sale = Sale::with('items')->findOrFail($id);

        return DB::transaction(function () use ($sale) {
            foreach ($sale->items as $item) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->first();

                if ($stock) {
                    $stock->quantity += $item->quantity;
                    $stock->save();
                }
            }

            $sale->delete();

            return response()->json([
                'message' => 'Sale deleted successfully'
            ]);
        });
    }



    /**
     * Generate Invoice
     */
    public function invoice($id)
    {
        $sale = Sale::with(['items.product', 'items.warehouse'])->findOrFail($id);
        $pdf = \PDF::loadView('pdf.sale_invoice', compact('sale'));
        return $pdf->download('Sale_' . $sale->invoice_number . '.pdf');
    }
}