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
        $purchases = Purchase::with(['supplier', 'items.product'])
            ->orderBy('purchase_date', 'desc')
            ->paginate(10);

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
        $purchase = Purchase::findOrFail($id);

        $request->validate([
            'invoice_number' => 'required|string|unique:purchases,invoice_number,' . $id,
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

        return DB::transaction(function () use ($request, $purchase) {
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

            // 3. Calculate new total and update purchase
            $total = 0;
            foreach ($request->items as $item) {
                $total += $item['quantity'] * $item['unit_price'];
            }

            $purchase->update([
                'invoice_number' => $request->invoice_number,
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $total,
                'notes' => $request->notes,
            ]);

            // 4. Create new items and apply new stock changes
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