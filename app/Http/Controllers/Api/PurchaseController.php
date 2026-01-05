<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;



/**
 * @group Purchases
 * APIs for purchasing stock from suppliers
 */
class PurchaseController extends Controller
{
    /**
     * List Purchases
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $limit = $request->query('limit', 10);

        $query = Purchase::with(['supplier', 'items.product']);

        // Filtering by status
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Filtering by date range
        if ($request->filled('from_date')) {
            $query->whereDate('purchase_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('purchase_date', '<=', $request->to_date);
        }

        // Filtering by invoice number
        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        // Search by invoice or item name
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('items.product', function ($pq) use ($searchTerm) {
                      $pq->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        $purchases = $query->orderBy('purchase_date', 'desc')
            ->paginate($limit);

        return response()->json([
            'message' => 'Purchases fetched successfully',
            'data' => $purchases->items(),
            'pagination' => [
                'total' => $purchases->total(),
                'per_page' => $purchases->perPage(),
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
            ]
        ]);
    }

    /**
     * List Active Purchases
     * 
     * Returns all active purchases without pagination.
     */
    public function activePurchases(Request $request)
    {
        $query = Purchase::with(['supplier', 'items.product'])->where('is_active', true);

        // Date range
        if ($request->filled('from_date')) {
            $query->whereDate('purchase_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('purchase_date', '<=', $request->to_date);
        }

        // Invoice search
        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        // Search by item name or invoice
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('items.product', function ($pq) use ($searchTerm) {
                      $pq->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        $purchases = $query->orderBy('purchase_date', 'desc')->get();

        return response()->json([
            'message' => 'Active purchases fetched successfully',
            'data' => $purchases
        ]);
    }

    /**
     * Toggle Purchase Status
     */
    public function toggleStatus($id)
    {
        $purchase = Purchase::findOrFail($id);
        $purchase->is_active = !$purchase->is_active;
        $purchase->save();

        return response()->json([
            'message' => 'Purchase status toggled successfully',
            'data' => [
                'id' => $purchase->id,
                'is_active' => $purchase->is_active
            ]
        ]);
    }

    /**
     * Create Purchase
     * 
     * Add stock from supplier.
     * 
     * @bodyParam invoice_number string required
     * @bodyParam supplier_id string required
     * @bodyParam purchase_date date required
     * @bodyParam items array required
     * @bodyParam items.*.product_id string required
     * @bodyParam items.*.warehouse_id string required
     * @bodyParam items.*.quantity number required
     * @bodyParam items.*.unit_price number required
     * @bodyParam items.*.expiry_date date optional
     */
    public function store(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|string|unique:purchases,invoice_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $total = 0;
            foreach ($request->items as $item) {
                $total += $item['quantity'] * $item['unit_price'];
            }

            $purchase = Purchase::create([
                'invoice_number' => $request->invoice_number,
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $total,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $itemTotal,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);

                // Add to stock
                $stock = Stock::firstOrNew([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);

                $stock->quantity = ($stock->quantity ?? 0) + $item['quantity'];
                $stock->save();
            }

            return response()->json([
                'message' => 'Purchase recorded successfully',
                'data' => $purchase->load('items.product', 'supplier')
            ], 201);
        });
    }

    /**
     * Get Purchase
     * 
     * Show single purchase with items.
     * 
     * @urlParam id string required Purchase UUID
     */
    public function show($id)
    {
        $purchase = Purchase::with(['supplier', 'items.product', 'items.warehouse'])->findOrFail($id);

        return response()->json([
            'message' => 'Purchase retrieved successfully',
            'data' => $purchase
        ]);
    }

    /**
     * Update Purchase
     * 
     * Update purchase details and adjust stock.
     * 
     * @urlParam id string required Purchase UUID
     * @bodyParam invoice_number string required
     * @bodyParam supplier_id string required
     * @bodyParam purchase_date date required
     * @bodyParam items array required
     * @bodyParam items.*.product_id string required
     * @bodyParam items.*.warehouse_id string required
     * @bodyParam items.*.quantity number required
     * @bodyParam items.*.unit_price number required
     * @bodyParam items.*.expiry_date date optional
     */
    public function update(Request $request, $id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        $request->validate([
            'invoice_number' => 'sometimes|required|string|unique:purchases,invoice_number,' . $id,
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'purchase_date' => 'sometimes|required|date',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $purchase) {
            $updateData = $request->only(['invoice_number', 'supplier_id', 'purchase_date', 'notes']);

            if ($request->has('items')) {
                // 1. Revert old stock changes
                foreach ($purchase->items as $oldItem) {
                    $stock = Stock::where('product_id', $oldItem->product_id)
                        ->where('warehouse_id', $oldItem->warehouse_id)
                        ->where('expiry_date', $oldItem->expiry_date)
                        ->first();

                    if ($stock) {
                        $stock->quantity -= $oldItem->quantity;
                        $stock->save();
                    }
                }

                // 2. Delete existing items
                $purchase->items()->delete();

                // 3. Calculate new total
                $total = 0;
                foreach ($request->items as $item) {
                    $total += $item['quantity'] * $item['unit_price'];
                }
                $updateData['total_amount'] = $total;

                // 4. Update purchase top-level data
                $purchase->update($updateData);

                // 5. Create new items and apply new stock changes
                foreach ($request->items as $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];

                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $itemTotal,
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ]);

                    // Add to stock
                    $stock = Stock::firstOrNew([
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ]);

                    $stock->quantity = ($stock->quantity ?? 0) + $item['quantity'];
                    $stock->save();
                }
            } else {
                // Just update top-level information
                $purchase->update($updateData);
            }

            return response()->json([
                'message' => 'Purchase updated successfully',
                'data' => $purchase->load('items.product', 'supplier')
            ]);
        });
    }

    /**
     * Delete Purchase
     * 
     * Delete purchase and revert stock changes.
     * 
     * @urlParam id string required Purchase UUID
     */
    public function destroy($id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        return DB::transaction(function () use ($purchase) {
            // Revert stock changes
            foreach ($purchase->items as $item) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->where('expiry_date', $item->expiry_date)
                    ->first();

                if ($stock) {
                    $stock->quantity -= $item->quantity;
                    $stock->save();
                }
            }

            $purchase->delete();

            return response()->json([
                'message' => 'Purchase deleted successfully'
            ]);
        });
    }

    public function invoice($id)
{
    $purchase = Purchase::with(['supplier', 'items.product', 'items.warehouse'])->findOrFail($id);

    $pdf = PDF::loadView('pdf.purchase_invoice', compact('purchase'));

    return $pdf->download('Purchase_' . $purchase->invoice_number . '.pdf');
}
}