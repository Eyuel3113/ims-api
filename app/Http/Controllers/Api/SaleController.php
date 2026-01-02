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
 * Simple POS style sales — reduce stock
 */
class SaleController extends Controller
{
    /**
     * List Sales
     */
    public function index(Request $request)
    {
        $sales = Sale::with(['items.product'])
            ->orderBy('sale_date', 'desc')
            ->paginate(10);

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
     * Create Sale — POS Style
     * 
     * Sell products — reduce stock.
     * 
     * @bodyParam invoice_number string required
     * @bodyParam sale_date date required Default today
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

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $itemTotal,
                ]);

                // Reduce stock
                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $item['warehouse_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    return response()->json(['message' => "Insufficient stock for product ID: {$item['product_id']}"], 400);
                }

                $stock->quantity -= $item['quantity'];
                $stock->save();

                // Low stock alert
                $product = $stock->product;
                if ($product->min_stock > 0 && $stock->quantity <= $product->min_stock) {
                    $user = Auth::user(); // Fix undefined variable
                    if ($user) {
                         // $user->notify(new LowStockNotification($product, $stock->quantity));
                    }
                }
            }

            return response()->json([
                'message' => 'Sale recorded successfully',
                'data' => $sale->load('items.product')
            ], 201);
        });
    }

    // show, destroy...
}