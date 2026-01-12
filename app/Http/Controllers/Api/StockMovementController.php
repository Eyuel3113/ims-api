<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;

/**
 * @group Stock History
 * Full audit trail of stock movements
 */
class StockMovementController extends Controller
{
    /**
     * List Stock Movements
     * 
     * @queryParam product_id string optional
     * @queryParam warehouse_id string optional
     * @queryParam type string optional purchase, sale, adjustment
     * @queryParam limit integer optional Default 20
     */
    public function index(Request $request)
    {
        $query = StockMovement::with(['product', 'warehouse'])
            ->orderBy('created_at', 'desc');

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $movements = $query->paginate($request->limit ?? 20);

        return response()->json([
            'message' => 'Stock movements fetched successfully',
            'data' => $movements->items(),
            'pagination' => [
                'total' => $movements->total(),
                'per_page' => $movements->perPage(),
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
            ]
        ]);
    }

    /**
     * Create Manual Stock Movement
     * 
     * Manually adjust stock for reasons like damage, loss, or found items.
     * 
     * @bodyParam product_id string required
     * @bodyParam warehouse_id string required
     * @bodyParam quantity number required Positive value.
     * @bodyParam type string required damage, lost, found, adjustment
     * @bodyParam notes string optional
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'type' => 'required|in:damage,lost,found,adjustment',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
                $product = \App\Models\Product::findOrFail($request->product_id);
                $warehouse = \App\Models\Warehouse::findOrFail($request->warehouse_id);

                // Find or create stock record
                $stock = \App\Models\Stock::where('product_id', $request->product_id)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->first();

                if (!$stock && in_array($request->type, ['found', 'adjustment'])) {
                     $stock = new \App\Models\Stock([
                        'product_id' => $request->product_id,
                        'warehouse_id' => $request->warehouse_id,
                        'quantity' => 0
                    ]);
                }

                if (!$stock) {
                     throw new \Exception("Stock record not found for this product in the specified warehouse.");
                }

                $qtyChange = $request->quantity;
                // If damage or lost, it's a reduction
                if (in_array($request->type, ['damage', 'lost'])) {
                    $qtyChange = -$request->quantity;
                }

                // Check for insufficient stock if reducing
                if ($qtyChange < 0 && ($stock->quantity + $qtyChange) < 0) {
                    throw new \Exception("Insufficient stock in this warehouse to record this " . $request->type);
                }

                $stock->quantity += $qtyChange;
                $stock->save();

                // Record movement
                $movement = StockMovement::create([
                    'product_id' => $request->product_id,
                    'warehouse_id' => $request->warehouse_id,
                    'quantity' => $qtyChange,
                    'type' => $request->type,
                    'reference_type' => 'Manual Adjustment',
                    'notes' => $request->notes,
                ]);

                // Check for low stock notification
                if ($qtyChange < 0) {
                    $totalStock = \App\Models\Stock::where('product_id', $product->id)->sum('quantity');
                    if ($product->min_stock > 0 && $totalStock <= $product->min_stock) {
                        $users = \App\Models\User::all();
                        \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\LowStockNotification($product, $totalStock));
                    }
                }

                return response()->json([
                    'message' => 'Stock movement recorded successfully',
                    'data' => $movement->load(['product', 'warehouse'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}