<?php

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Payment;
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
    $customer = Customer::create(['code' => 'REF_CUST_'.$suffix, 'name' => 'Refined Cust '.$suffix, 'phone' => '456'.$suffix]);
    $warehouse = Warehouse::first();
    $product = Product::first();

    echo "--- Testing Refined Payment Features ---\n";

    // 1. Create Sale with Due
    $sale = Sale::create([
        'invoice_number' => 'INV-REF-'.$suffix,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'sale_date' => date('Y-m-d'),
        'total_amount' => 100,
        'tax_amount' => 15,
        'grand_total' => 115,
        'paid_amount' => 0,
        'due_amount' => 115,
        'payment_status' => 'unpaid',
        'payment_method' => 'cash'
    ]);

    $controller = app(\App\Http\Controllers\Api\PaymentController::class);

    // 2. Test Overpayment Restriction
    echo "Testing Overpayment (paying 120 for 115 due)...\n";
    $reqOver = new \Illuminate\Http\Request([
        'payable_type' => 'sale',
        'payable_id' => $sale->id,
        'amount' => 120,
        'payment_date' => date('Y-m-d'),
        'payment_method' => 'cash'
    ]);
    $respOver = $controller->store($reqOver);
    if ($respOver->getStatusCode() === 422) {
        echo "Overpayment Restricted: PASS\n";
    } else {
        echo "Overpayment Restriction: FAIL\n";
    }

    // 3. Test Payables Listing
    echo "Testing Active Payables List...\n";
    $respPayables = $controller->payables(new \Illuminate\Http\Request());
    $dataPayables = json_decode($respPayables->getContent(), true);
    $found = collect($dataPayables['data'])->contains('invoice_number', 'INV-REF-'.$suffix);
    if ($found) {
        echo "Payable found in list: PASS\n";
    } else {
        echo "Payable found in list: FAIL\n";
    }

    // 4. Test Filtering
    echo "Testing Filtering by Type (sale)...\n";
    // First record a valid payment to have something to filter
    $reqValid = new \Illuminate\Http\Request([
        'payable_type' => 'sale',
        'payable_id' => $sale->id,
        'amount' => 115,
        'payment_date' => date('Y-m-d'),
        'payment_method' => 'cash'
    ]);
    $controller->store($reqValid);

    $reqFilter = new \Illuminate\Http\Request(['type' => 'sale']);
    $respFilter = $controller->index($reqFilter);
    $dataFilter = json_decode($respFilter->getContent(), true);
    $allSales = collect($dataFilter['data'])->every('payable_type', Sale::class);
    if ($allSales && count($dataFilter['data']) > 0) {
        echo "Filtering by Type: PASS\n";
    } else {
        echo "Filtering by Type: FAIL\n";
    }

    // 5. Test Adjusting Paid Transaction Restriction
    echo "Testing Deletion Restriction for Paid Transaction...\n";
    $payId = $dataFilter['data'][0]['id'];
    $respDel = $controller->destroy($payId);
    if ($respDel->getStatusCode() === 422) {
        echo "Deletion Restricted: PASS\n";
    } else {
        echo "Deletion Restriction: FAIL\n";
    }

    echo "\n--- REFINED VERIFICATION FINISHED ---\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
