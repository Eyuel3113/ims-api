<?php

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Warehouse;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $suffix = rand(1000, 9999);

    // 1. Create Customer and Sale
    $customer = Customer::create([
        'code' => 'HIST_CUST_' . $suffix,
        'name' => 'History Customer ' . $suffix,
        'phone' => '111' . $suffix . '111'
    ]);
    
    $sale = Sale::create([
        'invoice_number' => 'INV-HIST-' . $suffix,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name, // Should work now
        'sale_date' => date('Y-m-d'),
        'total_amount' => 50,
        'tax_amount' => 5,
        'grand_total' => 55,
        'payment_method' => 'cash',
        'is_active' => true
    ]);
    
    // 2. Create Supplier and Purchase
    $supplier = Supplier::create([
        'code' => 'HIST_SUPP_' . $suffix,
        'name' => 'History Supplier ' . $suffix,
        'phone' => '222' . $suffix . '222'
    ]);

    // Create purchase (assuming purchase structure similar to sale for this test)
    // Checking Purchase model from previous turns, it uses supplier_id and supplier_name
    $purchase = Purchase::create([
        'invoice_number' => 'PUR-HIST-' . $suffix,
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
        'purchase_date' => date('Y-m-d'),
        'total_amount' => 100,
        'tax_amount' => 10,
        'grand_total' => 110,
        'status' => 'pending',
        'is_active' => true
    ]);

    // 3. Verify Relationships
    $customerWithSales = Customer::with('sales')->find($customer->id);
    $supplierWithPurchases = Supplier::with('purchases')->find($supplier->id);

    echo "Customer Sales Count: " . $customerWithSales->sales->count() . "\n";
    echo "Supplier Purchases Count: " . $supplierWithPurchases->purchases->count() . "\n";

    if ($customerWithSales->sales->count() > 0 && $supplierWithPurchases->purchases->count() > 0) {
        echo "VERIFICATION SUCCESSFUL\n";
    } else {
        echo "VERIFICATION FAILED\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // echo $e->getTraceAsString();
}
