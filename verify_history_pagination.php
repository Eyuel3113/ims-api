<?php

use App\Models\Sale;
use App\Models\Payment;
use App\Models\Customer;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $suffix = rand(1000, 9999);
    $customer = Customer::create(['code' => 'HIST_CUST_'.$suffix, 'name' => 'Hist Cust '.$suffix, 'phone' => '333'.$suffix]);

    echo "--- Testing Per-Transaction History Pagination & Filtering ---\n";

    // 1. Create Sale
    $sale = Sale::create([
        'invoice_number' => 'S-HIST-'.$suffix,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'sale_date' => '2024-01-01',
        'grand_total' => 500,
        'due_amount' => 500,
        'payment_status' => 'unpaid',
        'payment_method' => 'cash'
    ]);

    // 2. Create 3 payments on different dates
    Payment::create(['payable_type' => Sale::class, 'payable_id' => $sale->id, 'amount' => 10, 'payment_date' => '2024-01-01', 'payment_method' => 'cash']);
    Payment::create(['payable_type' => Sale::class, 'payable_id' => $sale->id, 'amount' => 20, 'payment_date' => '2024-02-01', 'payment_method' => 'cash']);
    Payment::create(['payable_type' => Sale::class, 'payable_id' => $sale->id, 'amount' => 30, 'payment_date' => '2024-03-01', 'payment_method' => 'cash']);

    $controller = app(\App\Http\Controllers\Api\PaymentController::class);

    // 3. Test Pagination (limit 2)
    echo "Testing pagination (limit 2)...\n";
    $reqPag = new \Illuminate\Http\Request(['limit' => 2]);
    $respPag = $controller->payableHistory($reqPag, $sale->id, 'sale');
    $dataPag = json_decode($respPag->getContent(), true);
    if (count($dataPag['data']) === 2 && $dataPag['pagination']['total'] === 3) {
        echo "Pagination (limit 2): PASS\n";
    } else {
        echo "Pagination (limit 2): FAIL. Count: " . count($dataPag['data']) . ", Total: " . $dataPag['pagination']['total'] . "\n";
    }

    // 4. Test Date Filter (from 2024-01-15 to 2024-02-15)
    echo "Testing date filter (from 2024-01-15 to 2024-02-15)...\n";
    $reqDate = new \Illuminate\Http\Request(['from_date' => '2024-01-15', 'to_date' => '2024-02-15']);
    $respDate = $controller->payableHistory($reqDate, $sale->id, 'sale');
    $dataDate = json_decode($respDate->getContent(), true);
    // Should contain only the second payment (Feb 1)
    if (count($dataDate['data']) === 1 && $dataDate['data'][0]['amount'] == 20) {
        echo "Date Filtering: PASS\n";
    } else {
        echo "Date Filtering: FAIL. Count: " . count($dataDate['data']) . "\n";
    }

    echo "\n--- PER-TRANSACTION HISTORY VERIFICATION FINISHED ---\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
