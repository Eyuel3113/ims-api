<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Dashboard Analytics
     * 
     * Key metrics for inventory dashboard.
     * 
     * @group Analytics
     */
    public function dashboard()
    {
        $totalProducts = Product::where('is_active', true)->count();
        $lowStockProducts = Product::where('is_active', true)
            ->whereRaw('min_stock > (SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE stocks.product_id = products.id)')
            ->count();

        $totalWarehouses = Warehouse::where('is_active', true)->count();
        $totalSuppliers = Supplier::where('is_active', true)->count();

        $totalStockValue = Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->selectRaw('SUM(stocks.quantity * products.purchase_price) as value')
            ->value('value') ?? 0;

        $todaySales = Sale::whereDate('sale_date', today())->sum('total_amount');
        $todayPurchases = Purchase::whereDate('purchase_date', today())->sum('total_amount');

        $topProducts = Stock::with('product')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->pluck('product.name', 'total');

        return response()->json([
            'message' => 'Dashboard analytics',
            'data' => [
                'total_products' => $totalProducts,
                'low_stock_products' => $lowStockProducts,
                'total_warehouses' => $totalWarehouses,
                'total_suppliers' => $totalSuppliers,
                'total_stock_value' => number_format($totalStockValue, 2),
                'today_sales' => number_format($todaySales, 2),
                'today_purchases' => number_format($todayPurchases, 2),
                'top_products' => $topProducts,
            ]
        ]);
    }

    /**
     * Stock Value by Warehouse
     */
    public function stockByWarehouse()
    {
        $data = Warehouse::with(['stocks.product'])
            ->get()
            ->map(function ($warehouse) {
                $value = $warehouse->stocks->sum(function ($stock) {
                    return $stock->quantity * ($stock->product->purchase_price ?? 0);
                });

                return [
                    'name' => $warehouse->name,
                    'value' => round($value, 2)
                ];
            });

        return response()->json([
            'message' => 'Stock value by warehouse',
            'data' => $data
        ]);
    }

    /**
     * Monthly Sales vs Purchase
     */
    public function monthlyTrend()
    {
        $months = [];
        for ($i = 7; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months[] = $date->format('Y-m');
        }

        $sales = Sale::selectRaw('DATE_FORMAT(sale_date, "%Y-%m") as month, SUM(total_amount) as total')
            ->whereIn(DB::raw('DATE_FORMAT(sale_date, "%Y-%m")'), $months)
            ->groupBy('month')
            ->pluck('total', 'month');

        $purchases = Purchase::selectRaw('DATE_FORMAT(purchase_date, "%Y-%m") as month, SUM(total_amount) as total')
            ->whereIn(DB::raw('DATE_FORMAT(purchase_date, "%Y-%m")'), $months)
            ->groupBy('month')
            ->pluck('total', 'month');

        $data = collect($months)->map(function ($month) use ($sales, $purchases) {
            return [
                'month' => $month,
                'sales' => $sales[$month] ?? 0,
                'purchases' => $purchases[$month] ?? 0,
            ];
        });

        return response()->json([
            'message' => 'Monthly sales vs purchase trend',
            'data' => $data
        ]);
    }
}