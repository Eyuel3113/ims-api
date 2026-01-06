<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Purchase;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * @group Reports
 * API Endpoints for generating business reports and analytics.
 */
class ReportsController extends Controller
{
    /**
     * Overview Report
     * 
     * Get high-level business metrics including revenue, profit, and sales volume.
     */
    public function overview()
    {
        $totalRevenue = Sale::sum('total_amount');
        
        $totalProfit = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->selectRaw('SUM(sale_items.total_price - (sale_items.quantity * products.purchase_price)) as profit')
            ->value('profit') ?? 0;

        $itemsSold = SaleItem::sum('quantity');

        $averageSaleValue = Sale::avg('total_amount') ?? 0;

        return response()->json([
            'message' => 'Overview report fetched successfully',
            'data' => [
                'total_revenue' => round((float) $totalRevenue, 2),
                'total_profit' => round((float) $totalProfit, 2),
                'items_sold' => round((float) $itemsSold, 2),
                'average_purchase_value' => round((float) $averageSaleValue, 2), // Labeled as requested
            ]
        ]);
    }

    /**
     * Sales Report
     * 
     * Get top selling products and breakdown by payment methods.
     */
    public function sales()
    {
        $topSellingProducts = SaleItem::join('products', 'sale_items.product_id', '=', 'products.id')
            ->select('products.name', 'products.code', DB::raw('SUM(sale_items.quantity) as total_sold'))
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        $paymentMethods = Sale::select('payment_method', DB::raw('SUM(total_amount) as total_amount'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'message' => 'Sales report fetched successfully',
            'data' => [
                'top_selling_products' => $topSellingProducts,
                'payment_methods' => $paymentMethods,
            ]
        ]);
    }

    /**
     * Inventory Report
     * 
     * Get current stock value and list of low stock items.
     */
    public function inventory()
    {
        $currentStockValue = Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->selectRaw('SUM(stocks.quantity * products.purchase_price) as value')
            ->value('value') ?? 0;

        $lowStockItems = Product::where('is_active', true)
            ->whereRaw('min_stock > (SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE stocks.product_id = products.id)')
            ->get(['id', 'name', 'code', 'min_stock'])
            ->map(function ($product) {
                $product->current_stock = $product->stocks()->sum('quantity');
                return $product;
            });

        return response()->json([
            'message' => 'Inventory report fetched successfully',
            'data' => [
                'current_stock_value' => round((float) $currentStockValue, 2),
                'low_stock_items' => $lowStockItems,
            ]
        ]);
    }

    /**
     * Profit & Loss Report
     * 
     * Get monthly breakdown of revenue, COGS, and gross profit for the last 12 months.
     */
    public function profitLoss()
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = now()->subMonthsNoOverflow($i)->format('Y-m');
        }

        $data = collect($months)->map(function ($month) {
            $date = Carbon::parse($month . '-01');
            
            $revenue = Sale::whereMonth('sale_date', $date->month)
                ->whereYear('sale_date', $date->year)
                ->sum('total_amount');

            $cogs = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->whereMonth('sales.sale_date', $date->month)
                ->whereYear('sales.sale_date', $date->year)
                ->selectRaw('SUM(sale_items.quantity * products.purchase_price) as cogs')
                ->value('cogs') ?? 0;

            $profit = $revenue - $cogs;

            return [
                'month' => $month,
                'revenue' => round((float) $revenue, 2),
                'cogs' => round((float) $cogs, 2),
                'gross_profit' => round((float) $profit, 2),
            ];
        });

        return response()->json([
            'message' => 'Profit & Loss report fetched successfully',
            'data' => $data
        ]);
    }
}
