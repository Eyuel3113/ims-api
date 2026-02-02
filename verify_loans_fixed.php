<?php

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Stock;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Setup boilerplate
    $customer = Customer::firstOrCreate(['code' => 'LOAN_CUST_1'], ['name' => 'Loan Customer 1', 'phone' => '1234567891']);
    $supplier = Supplier::firstOrCreate(['code' => 'LOAN_SUPP_1'], ['name' => 'Loan Supplier 1', 'phone' => '1234567892']);
    $warehouse = Warehouse::firstOrCreate(['code' => 'WH_LOAN'], ['name' => 'Loan Warehouse']);
    $product = Product::firstOrCreate(['code' => 'PROD_LOAN'], [
        'name' => 'Loan Product',
        'category_id' => \App\Models\Category::first()->id,
        'purchase_price' => 100,
        'selling_price' => 200,
        'min_stock' => 5,
        'unit' => 'pcs' // Added missing unit field
    ]);
    Stock::firstOrCreate(['product_id' => $product->id, 'warehouse_id' => $warehouse->id], ['quantity' => 100]);

    echo "--- Testing Sales Loans ---\n";
    $testSales = [
        ['paid' => 230, 'expected_status' => 'paid', 'desc' => 'Full Payment'],
        ['paid' => 100, 'expected_status' => 'partial', 'desc' => 'Partial Payment'],
        ['paid' => 0, 'expected_status' => 'unpaid', 'desc' => 'Zero Payment'],
    ];

    foreach ($testSales as $test) {
        $invoice = 'INV-LOAN-' . rand(1000, 9999);
        $request = new \Illuminate\Http\Request([
            'invoice_number' => $invoice,
            'sale_date' => date('Y-m-d'),
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'paid_amount' => $test['paid'],
            'items' => [['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1]]
        ]);

        $controller = app(\App\Http\Controllers\Api\SaleController::class);
        $response = $controller->store($request);
        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() === 201) {
            $sale = Sale::find($data['data']['id']);
            echo "{$test['desc']}: Paid={$sale->paid_amount}, Due={$sale->due_amount}, Status={$sale->payment_status} -> " . ($sale->payment_status === $test['expected_status'] ? "PASS" : "FAIL") . "\n";
        } else {
            echo "{$test['desc']}: Failed to create sale! Error: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    }

    echo "\n--- Testing Purchase Loans ---\n";
    $testPurchases = [
        ['paid' => 115, 'expected_status' => 'paid', 'desc' => 'Full Payment'],
        ['paid' => 50, 'expected_status' => 'partial', 'desc' => 'Partial Payment'],
        ['paid' => 0, 'expected_status' => 'unpaid', 'desc' => 'Zero Payment'],
    ];

    foreach ($testPurchases as $test) {
        $invoice = 'PUR-LOAN-' . rand(1000, 9999);
        $request = new \Illuminate\Http\Request([
            'invoice_number' => $invoice,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'purchase_date' => date('Y-m-d'),
            'paid_amount' => $test['paid'],
            'items' => [['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1]]
        ]);

        $controller = app(\App\Http\Controllers\Api\PurchaseController::class);
        $response = $controller->store($request);
        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() === 201) {
            $purchase = Purchase::find($data['data']['id']);
            echo "{$test['desc']}: Paid={$purchase->paid_amount}, Due={$purchase->due_amount}, Status={$purchase->payment_status} -> " . ($purchase->payment_status === $test['expected_status'] ? "PASS" : "FAIL") . "\n";
        } else {
            echo "{$test['desc']}: Failed to create purchase! Error: " . ($data['message'] ?? 'Unknown error') . "\n";
        }
    }

    echo "\n--- VERIFICATION FINISHED ---\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
