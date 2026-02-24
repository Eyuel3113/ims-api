<?php

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Payment;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Payables Verification...\n";

DB::beginTransaction();

try {
    // 1. Setup Data
    $supplier = Supplier::firstOrCreate(
        ['email' => 'test_supplier_payables@example.com'],
        ['name' => 'Test Supplier Payables', 'phone' => '1234567890', 'address' => 'Test Address', 'code' => 'SUP-PAY-' . time()]
    );

    // 2. Create Active Purchase (Should be in payables)
    $activePurchase = Purchase::create([
        'invoice_number' => 'INV-ACTIVE-' . time(),
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
        'purchase_date' => now(),
        'status' => 'pending',
        'total_amount' => 100,
        'tax_amount' => 0,
        'grand_total' => 100,
        'paid_amount' => 0,
        'due_amount' => 100,
        'payment_status' => 'unpaid',
    ]);
    
    // 3. Create Cancelled Purchase (Should NOT be in payables)
    $cancelledPurchase = Purchase::create([
        'invoice_number' => 'INV-CANCELLED-' . time(),
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
        'purchase_date' => now(),
        'status' => 'cancelled',
        'total_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 0,
        'paid_amount' => 0,
        'due_amount' => 0,
        'payment_status' => 'unpaid',
    ]);
    
    echo "Created Active Purchase: {$activePurchase->invoice_number}\n";
    echo "Created Cancelled Purchase: {$cancelledPurchase->invoice_number}\n";

    // 4. Call payables
    $controller = new PaymentController();
    $request = Request::create('/api/payables', 'GET', ['type' => 'purchase']);
    
    $response = $controller->payables($request);
    $data = $response->getData(true); // Get JSON data as array
    
    echo "Called Payables API.\n";
    
    $items = $data['data'];
    
    $foundActive = false;
    $foundCancelled = false;
    
    foreach ($items as $item) {
        if ($item['invoice_number'] === $activePurchase->invoice_number) {
            $foundActive = true;
        }
        if ($item['invoice_number'] === $cancelledPurchase->invoice_number) {
            $foundCancelled = true;
        }
    }
    
    echo "Found Active: " . ($foundActive ? 'YES' : 'NO') . "\n";
    echo "Found Cancelled: " . ($foundCancelled ? 'YES' : 'NO') . "\n";

    if (!$foundActive) throw new Exception("Active purchase not found in payables list");
    if ($foundCancelled) throw new Exception("Cancelled purchase found in payables list (should be excluded)");

    echo "VERIFICATION SUCCESSFUL!\n";

} catch (Exception $e) {
    file_put_contents('payables_verification_error.log', $e->getMessage() . "\n" . $e->getTraceAsString());
    echo "VERIFICATION FAILED: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack(); // Always rollback test data
    echo "\nRolled back changes.\n";
}
