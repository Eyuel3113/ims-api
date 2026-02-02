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
    $customer = Customer::create(['code' => 'FIX_CUST_'.$suffix, 'name' => 'Fix Cust '.$suffix, 'phone' => '789'.$suffix]);
    
    // 1. Create Sale
    $sale = Sale::create([
        'invoice_number' => 'INV-FIX-'.$suffix,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'sale_date' => date('Y-m-d'),
        'total_amount' => 100,
        'tax_amount' => 0,
        'grand_total' => 100,
        'paid_amount' => 0,
        'due_amount' => 100,
        'payment_status' => 'unpaid',
        'payment_method' => 'cash'
    ]);

    $controller = app(\App\Http\Controllers\Api\PaymentController::class);

    // 2. Test optional payment_date
    echo "Testing optional payment_date...\n";
    $reqDate = new \Illuminate\Http\Request([
        'payable_type' => 'sale',
        'payable_id' => $sale->id,
        'amount' => 10,
        'payment_method' => 'cash'
    ]);
    $respDate = $controller->store($reqDate);
    if ($respDate->getStatusCode() === 201) {
        $pay = json_decode($respDate->getContent(), true)['data'];
        echo "Payment created without date: PASS (Date: {$pay['payment_date']})\n";
    } else {
        echo "Payment created without date: FAIL\n";
    }

    // 3. Test payableHistory route logic (manually invoking with swapped args as Laravel would)
    echo "Testing payableHistory argument order...\n";
    // Laravel injects {id} then default {type}
    $respHistory = $controller->payableHistory($sale->id, 'sale');
    if ($respHistory->getStatusCode() === 200) {
        echo "History fetch with correct argument order (id first): PASS\n";
    } else {
        echo "History fetch: FAIL\n";
    }

    echo "\n--- BUG FIX VERIFICATION FINISHED ---\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
