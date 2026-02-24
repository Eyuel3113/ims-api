<?php

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Payment;
use App\Http\Controllers\Api\PurchaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting Verification...\n";

DB::beginTransaction();

try {
    // 1. Setup Data
    $supplier = Supplier::firstOrCreate(
        ['email' => 'test_supplier@example.com'],
        ['name' => 'Test Supplier', 'phone' => '1234567890', 'address' => 'Test Address', 'code' => 'SUP-' . time()]
    );

    $product = Product::first();
    if (!$product) {
        $product = Product::create([
            'name' => 'Test Product', 'sku' => 'TEST-SKU-' . time(),
            'purchase_price' => 10, 'selling_price' => 20, 'quantity' => 0, 'unit' => 'pc', 'category_id' => 1
        ]);
    }

    $warehouse = Warehouse::first();
    if (!$warehouse) {
        $warehouse = Warehouse::create(['name' => 'Test Warehouse', 'location' => 'Test Location']);
    }

    // 2. Create Purchase (Pending) with initial payment
    $purchase = Purchase::create([
        'invoice_number' => 'INV-TEST-' . time(),
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
        'purchase_date' => now(),
        'status' => 'pending',
        'total_amount' => 100,
        'tax_amount' => 0,
        'grand_total' => 100,
        'paid_amount' => 50,
        'due_amount' => 50,
        'payment_status' => 'partial',
    ]);
    
    echo "Created Purchase: {$purchase->id}\n";

    // 3. Create Payment
    $payment = Payment::create([
        'payable_type' => Purchase::class,
        'payable_id' => $purchase->id,
        'amount' => 50,
        'payment_date' => now(),
        'payment_method' => 'cash',
        'notes' => 'Initial payment',
    ]);
    
    echo "Created Payment: {$payment->id}\n";
    
    // Verify Payment Exists
    $count = Payment::where('payable_id', $purchase->id)->where('payable_type', Purchase::class)->count();
    echo "Payments before cancel: $count\n";

    if ($count !== 1) {
        throw new Exception("Setup failed: Payment not found.");
    }

    // 4. Call cancelStatus
    $controller = new PurchaseController();
    $response = $controller->cancelStatus($purchase->id);
    
    echo "Called cancelStatus.\n";

    // 5. Verify Results
    $purchase->refresh();
    
    echo "Purchase Status: {$purchase->status}\n";
    echo "Paid Amount: {$purchase->paid_amount}\n";
    echo "Due Amount: {$purchase->due_amount}\n";
    echo "Payment Status: {$purchase->payment_status}\n";

    $paymentCount = Payment::where('payable_id', $purchase->id)->where('payable_type', Purchase::class)->count();
    echo "Payments after cancel: $paymentCount\n";

    echo "Total Amount: {$purchase->total_amount}\n";
    echo "Tax Amount: {$purchase->tax_amount}\n";
    echo "Grand Total: {$purchase->grand_total}\n";

    if ($purchase->status !== 'cancelled') throw new Exception("Status not cancelled");
    if ($purchase->paid_amount != 0) throw new Exception("Paid amount not 0");
    if ($purchase->due_amount != 0) throw new Exception("Due amount not 0"); 
    if ($purchase->total_amount != 0) throw new Exception("Total amount not 0");
    if ($purchase->tax_amount != 0) throw new Exception("Tax amount not 0");
    if ($purchase->grand_total != 0) throw new Exception("Grand total not 0");
    if ($paymentCount !== 0) throw new Exception("Payments not deleted");

    echo "VERIFICATION SUCCESSFUL!\n";

} catch (Exception $e) {
    file_put_contents('verification_error.log', $e->getMessage() . "\n" . $e->getTraceAsString());
    echo "VERIFICATION FAILED: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack(); // Always rollback test data
    echo "\nRolled back changes.\n";
}
