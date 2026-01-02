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
}