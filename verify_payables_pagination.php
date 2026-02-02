<?php

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $suffix = rand(1000, 9999);
    $customer = Customer::create(['code' => 'PAG_CUST_'.$suffix, 'name' => 'Pag Cust '.$suffix, 'phone' => '111'.$suffix]);
    $supplier = Supplier::create(['code' => 'PAG_SUPP_'.$suffix, 'name' => 'Pag Supp '.$suffix, 'phone' => '222'.$suffix]);

    echo "--- Testing Payables Pagination & Filtering ---\n";

    // 1. Create multiple records with different dates
    Sale::create([
        'invoice_number' => 'S-PAG-1-'.$suffix,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'sale_date' => '2024-01-01',
        'grand_total' => 100,
        'due_amount' => 100,
        'payment_status' => 'unpaid',
        'payment_method' => 'cash'
    ]);

    Purchase::create([
        'invoice_number' => 'P-PAG-2-'.$suffix,
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
        'purchase_date' => '2024-02-01',
        'grand_total' => 200,
        'due_amount' => 200,
        'payment_status' => 'unpaid'
    ]);

    $controller = app(\App\Http\Controllers\Api\PaymentController::class);

    // 2. Test Pagination (limit 1)
    echo "Testing pagination (limit 1)...\n";
    $reqPag = new \Illuminate\Http\Request(['limit' => 1]);
    $respPag = $controller->payables($reqPag);
    $dataPag = json_decode($respPag->getContent(), true);
    if (count($dataPag['data']) === 1 && $dataPag['pagination']['total'] >= 2) {
        echo "Pagination (limit 1): PASS\n";
    } else {
        echo "Pagination (limit 1): FAIL. Count: " . count($dataPag['data']) . "\n";
    }

    // 3. Test Date Filter (from 2024-01-15)
    echo "Testing date filter (from 2024-01-15)...\n";
    $reqDate = new \Illuminate\Http\Request(['from_date' => '2024-01-15']);
    $respDate = $controller->payables($reqDate);
    $dataDate = json_decode($respDate->getContent(), true);
    // Should contain the purchase (Feb) but NOT the sale (Jan 1)
    $hasPurchase = collect($dataDate['data'])->contains('invoice_number', 'P-PAG-2-'.$suffix);
    $hasSale = collect($dataDate['data'])->contains('invoice_number', 'S-PAG-1-'.$suffix);
    if ($hasPurchase && !$hasSale) {
        echo "Date Filtering: PASS\n";
    } else {
        echo "Date Filtering: FAIL. HasPurchase: " . ($hasPurchase?'Yes':'No') . ", HasSale: " . ($hasSale?'Yes':'No') . "\n";
    }

    // 4. Test Type Filter (purchase)
    echo "Testing type filter (purchase)...\n";
    $reqType = new \Illuminate\Http\Request(['type' => 'purchase']);
    $respType = $controller->payables($reqType);
    $dataType = json_decode($respType->getContent(), true);
    $onlyPurchases = collect($dataType['data'])->every('type', 'purchase');
    if ($onlyPurchases && count($dataType['data']) > 0) {
        echo "Type Filtering: PASS\n";
    } else {
        echo "Type Filtering: FAIL\n";
    }

    echo "\n--- PAGINATION VERIFICATION FINISHED ---\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
