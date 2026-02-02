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
    $customer = Customer::create(['code' => 'INC_CUST_'.$suffix, 'name' => 'Inc Cust '.$suffix, 'phone' => '111'.$suffix]);
    
    echo "--- Testing Inclusive Payables (Paid & Unpaid) ---\n";

    // 1. Create a PAID Sale
    Sale::create([
        'invoice_number' => 'S-PAID-'.$suffix,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'sale_date' => date('Y-m-d'),
        'grand_total' => 100,
        'paid_amount' => 100,
        'due_amount' => 0,
        'payment_status' => 'paid',
        'payment_method' => 'cash'
    ]);

    // 2. Create an UNPAID Sale
    Sale::create([
        'invoice_number' => 'S-UNPAID-'.$suffix,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'sale_date' => date('Y-m-d'),
        'grand_total' => 200,
        'paid_amount' => 0,
        'due_amount' => 200,
        'payment_status' => 'unpaid',
        'payment_method' => 'cash'
    ]);

    $controller = app(\App\Http\Controllers\Api\PaymentController::class);

    // 3. Verify both appear in the list
    $resp = $controller->payables(new \Illuminate\Http\Request());
    $data = json_decode($resp->getContent(), true);
    
    $hasPaid = collect($data['data'])->contains('invoice_number', 'S-PAID-'.$suffix);
    $hasUnpaid = collect($data['data'])->contains('invoice_number', 'S-UNPAID-'.$suffix);

    if ($hasPaid && $hasUnpaid) {
        echo "Inclusive Payables List (Paid & Unpaid): PASS\n";
    } else {
        echo "Inclusive Payables List: FAIL. HasPaid: " . ($hasPaid?'Yes':'No') . ", HasUnpaid: " . ($hasUnpaid?'Yes':'No') . "\n";
    }

    // 4. Test status filtering
    echo "Testing status filter (payment_status=paid)...\n";
    $reqFilter = new \Illuminate\Http\Request(['payment_status' => 'paid']);
    $respFilter = $controller->payables($reqFilter);
    $dataFilter = json_decode($respFilter->getContent(), true);
    $allPaid = collect($dataFilter['data'])->every('payment_status', 'paid');
    
    if ($allPaid && count($dataFilter['data']) > 0) {
        echo "Status Filtering (paid): PASS\n";
    } else {
        echo "Status Filtering (paid): FAIL\n";
    }

    echo "\n--- INCLUSIVE VERIFICATION FINISHED ---\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
