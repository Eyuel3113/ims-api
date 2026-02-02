<?php

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Supplier;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $suffix = rand(1000, 9999);
    $customer = Customer::create(['code' => 'C_HIST_'.$suffix, 'name' => 'History Cust '.$suffix, 'phone' => '555'.$suffix]);
    $supplier = Supplier::create(['code' => 'S_HIST_'.$suffix, 'name' => 'History Supp '.$suffix, 'phone' => '666'.$suffix]);

    echo "--- Testing Customer Sales History Pagination ---\n";
    // Create 3 sales
    for ($i=1; $i<=3; $i++) {
        Sale::create([
            'invoice_number' => "INV-H-{$i}-".$suffix,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'sale_date' => date('Y-m-d'),
            'grand_total' => 100 * $i,
            'due_amount' => 100 * $i,
            'payment_status' => 'unpaid',
            'payment_method' => 'cash'
        ]);
    }

    $cController = app(\App\Http\Controllers\Api\CustomerController::class);
    $reqC = new \Illuminate\Http\Request(['limit' => 2]);
    $respC = $cController->show($reqC, $customer->id);
    $dataC = json_decode($respC->getContent(), true)['data'];

    if (count($dataC['sales']) === 2 && $dataC['sales_pagination']['total'] === 3) {
        echo "Customer History Pagination (limit 2): PASS\n";
    } else {
        echo "Customer History Pagination: FAIL. Count: " . count($dataC['sales']) . ", Total: " . $dataC['sales_pagination']['total'] . "\n";
    }

    echo "\n--- Testing Supplier Purchase History Pagination ---\n";
    // Create 3 purchases
    for ($i=1; $i<=3; $i++) {
        Purchase::create([
            'invoice_number' => "PUR-H-{$i}-".$suffix,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'purchase_date' => date('Y-m-d'),
            'grand_total' => 100 * $i,
            'due_amount' => 100 * $i,
            'payment_status' => 'unpaid'
        ]);
    }

    $sController = app(\App\Http\Controllers\Api\SupplierController::class);
    $reqS = new \Illuminate\Http\Request(['limit' => 2]);
    $respS = $sController->show($reqS, $supplier->id);
    $dataS = json_decode($respS->getContent(), true)['data'];

    if (count($dataS['purchases']) === 2 && $dataS['purchases_pagination']['total'] === 3) {
        echo "Supplier History Pagination (limit 2): PASS\n";
    } else {
        echo "Supplier History Pagination: FAIL. Count: " . count($dataS['purchases']) . ", Total: " . $dataS['purchases_pagination']['total'] . "\n";
    }

    echo "\n--- HISTORY PAGINATION VERIFICATION FINISHED ---\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
