<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Expense;
use App\Models\Payment;

class RealisticDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@ims.com'],
            [
                'name' => 'System Administrator',
                'role' => 'admin',
                'password' => Hash::make('password123'),
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@ims.com'],
            [
                'name' => 'Sarah Jenkins',
                'role' => 'manager',
                'password' => Hash::make('password123'),
            ]
        );

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@ims.com'],
            [
                'name' => 'Michael Chang',
                'role' => 'cashier',
                'password' => Hash::make('password123'),
            ]
        );

        // 2. Categories
        $categoriesData = [
            [
                'name' => 'Electronics & Gadgets',
                'code' => 'CAT-ELEC',
                'description' => 'Computers, computer peripherals, smart gadgets, and hardware accessories.',
            ],
            [
                'name' => 'Office Supplies & Stationery',
                'code' => 'CAT-OFFICE',
                'description' => 'Printing paper, notebooks, desk organization, and ergonomic furniture.',
            ],
            [
                'name' => 'Food & Beverages',
                'code' => 'CAT-FOOD',
                'description' => 'Specialty roasted coffee, premium teas, and pantry snacks.',
            ],
            [
                'name' => 'Tools & Industrial Hardware',
                'code' => 'CAT-TOOLS',
                'description' => 'Power tools, hand tool kits, measurement equipment, and workshop gear.',
            ],
            [
                'name' => 'Health & Safety PPE',
                'code' => 'CAT-HEALTH',
                'description' => 'Nitrile gloves, certified respirators, sanitizers, and workplace first aid.',
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['code']] = Category::firstOrCreate(
                ['code' => $cat['code']],
                array_merge($cat, ['is_active' => true])
            );
        }

        // 3. Warehouses
        $warehousesData = [
            [
                'name' => 'Central Logistics Hub',
                'code' => 'WH-CHI',
                'address' => '100 Industrial Blvd, Dock 14, Chicago, IL 60601',
                'phone' => '+1 (312) 555-0143',
            ],
            [
                'name' => 'East Coast Fulfillment Center',
                'code' => 'WH-NJ',
                'address' => '450 Portside Ave, Bay 8, Newark, NJ 07101',
                'phone' => '+1 (201) 555-0188',
            ],
            [
                'name' => 'Metro Retail Depot',
                'code' => 'WH-NY',
                'address' => '12 Market Street, Lower Level, New York, NY 10001',
                'phone' => '+1 (212) 555-0199',
            ],
        ];

        $warehouses = [];
        foreach ($warehousesData as $wh) {
            $warehouses[$wh['code']] = Warehouse::firstOrCreate(
                ['code' => $wh['code']],
                array_merge($wh, ['is_active' => true])
            );
        }

        // 4. Suppliers
        $suppliersData = [
            [
                'name' => 'Apex Tech Solutions Inc.',
                'code' => 'SUP-APEX',
                'phone' => '+1 (408) 555-0211',
                'email' => 'sales@apextech.com',
                'address' => '88 Silicon Way, San Jose, CA 95110',
            ],
            [
                'name' => 'Global Office Supplies Ltd.',
                'code' => 'SUP-GLOBAL',
                'phone' => '+1 (206) 555-0244',
                'email' => 'orders@globaloffice.com',
                'address' => '320 Cedar Lane, Seattle, WA 98101',
            ],
            [
                'name' => 'Evergreen Agri & Roasters',
                'code' => 'SUP-EVERGREEN',
                'phone' => '+1 (503) 555-0277',
                'email' => 'distribution@evergreenagri.com',
                'address' => '15 Orchard Way, Portland, OR 97201',
            ],
            [
                'name' => 'Titan Industrial Tools Co.',
                'code' => 'SUP-TITAN',
                'phone' => '+1 (313) 555-0299',
                'email' => 'contact@titanhardware.com',
                'address' => '600 Forge Avenue, Detroit, MI 48201',
            ],
            [
                'name' => 'Guardian Health Supplies',
                'code' => 'SUP-GUARDIAN',
                'phone' => '+1 (617) 555-0322',
                'email' => 'b2b@guardiansafety.com',
                'address' => '42 Beacon Blvd, Boston, MA 02108',
            ],
        ];

        $suppliers = [];
        foreach ($suppliersData as $sup) {
            $suppliers[$sup['code']] = Supplier::firstOrCreate(
                ['code' => $sup['code']],
                array_merge($sup, ['is_active' => true])
            );
        }

        // 5. Customers
        $customersData = [
            [
                'name' => 'Nexus Enterprise Corp',
                'code' => 'CUST-NEXUS',
                'phone' => '+1 (212) 555-0411',
                'email' => 'procurement@nexuscorp.com',
                'address' => '500 Fifth Avenue, Floor 24, New York, NY 10110',
            ],
            [
                'name' => 'Horizon Retail Stores',
                'code' => 'CUST-HORIZON',
                'phone' => '+1 (312) 555-0433',
                'email' => 'accounts@horizonretail.com',
                'address' => '740 Michigan Ave, Chicago, IL 60611',
            ],
            [
                'name' => 'St. Jude Community Clinic',
                'code' => 'CUST-CLINIC',
                'phone' => '+1 (617) 555-0455',
                'email' => 'supplies@stjudeclinic.org',
                'address' => '120 Hospital Rd, Boston, MA 02115',
            ],
            [
                'name' => 'Swift Craft Studios LLC',
                'code' => 'CUST-SWIFT',
                'phone' => '+1 (415) 555-0477',
                'email' => 'orders@swiftstudios.io',
                'address' => '90 Mission St, San Francisco, CA 94105',
            ],
            [
                'name' => 'Walk-in Retail Customer',
                'code' => 'CUST-WALKIN',
                'phone' => '+1 (000) 000-0000',
                'email' => 'walkin@store.local',
                'address' => 'Front Desk Counter',
            ],
        ];

        $customers = [];
        foreach ($customersData as $cust) {
            $customers[$cust['code']] = Customer::firstOrCreate(
                ['code' => $cust['code']],
                array_merge($cust, ['is_active' => true])
            );
        }

        // 6. Products
        $productsData = [
            [
                'name' => 'Dell UltraSharp 27" 4K Monitor',
                'code' => 'PRD-MON-4K',
                'category_code' => 'CAT-ELEC',
                'unit' => 'pcs',
                'barcode' => '890100100001',
                'purchase_price' => 240.00,
                'selling_price' => 349.99,
                'min_stock' => 5,
                'has_expiry' => false,
                'is_vatable' => true,
            ],
            [
                'name' => 'Logitech MX Master 3S Wireless Mouse',
                'code' => 'PRD-MOU-MX',
                'category_code' => 'CAT-ELEC',
                'unit' => 'pcs',
                'barcode' => '890100100002',
                'purchase_price' => 58.00,
                'selling_price' => 89.99,
                'min_stock' => 10,
                'has_expiry' => false,
                'is_vatable' => true,
            ],
            [
                'name' => 'Anker 65W GaN Fast Charger',
                'code' => 'PRD-CHG-65W',
                'category_code' => 'CAT-ELEC',
                'unit' => 'pcs',
                'barcode' => '890100100003',
                'purchase_price' => 18.50,
                'selling_price' => 34.99,
                'min_stock' => 15,
                'has_expiry' => false,
                'is_vatable' => true,
            ],
            [
                'name' => 'Double A Multipurpose Paper A4 (500 Sheets)',
                'code' => 'PRD-PAP-A4',
                'category_code' => 'CAT-OFFICE',
                'unit' => 'ream',
                'barcode' => '890100100004',
                'purchase_price' => 4.20,
                'selling_price' => 7.50,
                'min_stock' => 50,
                'has_expiry' => false,
                'is_vatable' => false,
            ],
            [
                'name' => 'Pilot G2 Premium Gel Pens (Pack of 12)',
                'code' => 'PRD-PEN-G2',
                'category_code' => 'CAT-OFFICE',
                'unit' => 'box',
                'barcode' => '890100100005',
                'purchase_price' => 8.50,
                'selling_price' => 14.99,
                'min_stock' => 20,
                'has_expiry' => false,
                'is_vatable' => true,
            ],
            [
                'name' => 'Ergonomic Lumbar Mesh Office Chair',
                'code' => 'PRD-CHR-ERGO',
                'category_code' => 'CAT-OFFICE',
                'unit' => 'pcs',
                'barcode' => '890100100006',
                'purchase_price' => 85.00,
                'selling_price' => 149.00,
                'min_stock' => 4,
                'has_expiry' => false,
                'is_vatable' => true,
            ],
            [
                'name' => 'Organic Arabica Whole Coffee Beans (1kg)',
                'code' => 'PRD-COF-ARA',
                'category_code' => 'CAT-FOOD',
                'unit' => 'bag',
                'barcode' => '890100100007',
                'purchase_price' => 12.50,
                'selling_price' => 22.00,
                'min_stock' => 25,
                'has_expiry' => true,
                'is_vatable' => false,
            ],
            [
                'name' => 'Ceylon Earl Grey Tea (100 Bags)',
                'code' => 'PRD-TEA-EG',
                'category_code' => 'CAT-FOOD',
                'unit' => 'box',
                'barcode' => '890100100008',
                'purchase_price' => 5.20,
                'selling_price' => 9.50,
                'min_stock' => 25,
                'has_expiry' => true,
                'is_vatable' => false,
            ],
            [
                'name' => 'DeWalt 20V MAX Cordless Drill Kit',
                'code' => 'PRD-DRL-20V',
                'category_code' => 'CAT-TOOLS',
                'unit' => 'kit',
                'barcode' => '890100100009',
                'purchase_price' => 98.00,
                'selling_price' => 159.00,
                'min_stock' => 6,
                'has_expiry' => false,
                'is_vatable' => true,
            ],
            [
                'name' => 'Stanley 65-Piece Home Tool Set',
                'code' => 'PRD-TLS-65P',
                'category_code' => 'CAT-TOOLS',
                'unit' => 'set',
                'barcode' => '890100100010',
                'purchase_price' => 32.00,
                'selling_price' => 55.00,
                'min_stock' => 10,
                'has_expiry' => false,
                'is_vatable' => true,
            ],
            [
                'name' => 'Hospital Nitrile Exam Gloves (Box of 100)',
                'code' => 'PRD-GLV-NIT',
                'category_code' => 'CAT-HEALTH',
                'unit' => 'box',
                'barcode' => '890100100011',
                'purchase_price' => 5.50,
                'selling_price' => 11.50,
                'min_stock' => 30,
                'has_expiry' => true,
                'is_vatable' => true,
            ],
            [
                'name' => 'Instant Foaming Hand Sanitizer (500ml)',
                'code' => 'PRD-SAN-500',
                'category_code' => 'CAT-HEALTH',
                'unit' => 'bottle',
                'barcode' => '890100100012',
                'purchase_price' => 2.20,
                'selling_price' => 4.99,
                'min_stock' => 30,
                'has_expiry' => true,
                'is_vatable' => true,
            ],
        ];

        $products = [];
        foreach ($productsData as $prod) {
            $catCode = $prod['category_code'];
            unset($prod['category_code']);
            $prod['category_id'] = $categories[$catCode]->id;
            $prod['is_active'] = true;

            $products[$prod['code']] = Product::firstOrCreate(
                ['code' => $prod['code']],
                $prod
            );
        }

        // 7. Stocks & Initial Movements
        $centralWh = $warehouses['WH-CHI'];
        $eastWh = $warehouses['WH-NJ'];

        $stockDistributions = [
            'PRD-MON-4K'  => ['central' => 25, 'east' => 10],
            'PRD-MOU-MX'  => ['central' => 60, 'east' => 35],
            'PRD-CHG-65W' => ['central' => 80, 'east' => 40],
            'PRD-PAP-A4'  => ['central' => 300, 'east' => 150],
            'PRD-PEN-G2'  => ['central' => 120, 'east' => 60],
            'PRD-CHR-ERGO'=> ['central' => 15, 'east' => 8],
            'PRD-COF-ARA' => ['central' => 90, 'east' => 40],
            'PRD-TEA-EG'  => ['central' => 110, 'east' => 50],
            'PRD-DRL-20V' => ['central' => 20, 'east' => 12],
            'PRD-TLS-65P' => ['central' => 35, 'east' => 18],
            'PRD-GLV-NIT' => ['central' => 200, 'east' => 100],
            'PRD-SAN-500' => ['central' => 150, 'east' => 80],
        ];

        foreach ($stockDistributions as $prodCode => $dist) {
            $prod = $products[$prodCode];
            $expiry = $prod->has_expiry ? now()->addMonths(14)->toDateString() : null;

            // Central Warehouse Stock
            Stock::firstOrCreate(
                [
                    'product_id' => $prod->id,
                    'warehouse_id' => $centralWh->id,
                    'expiry_date' => $expiry,
                ],
                ['quantity' => $dist['central']]
            );

            // East Coast Warehouse Stock
            Stock::firstOrCreate(
                [
                    'product_id' => $prod->id,
                    'warehouse_id' => $eastWh->id,
                    'expiry_date' => $expiry,
                ],
                ['quantity' => $dist['east']]
            );
        }

        // 8. Realistic Purchases
        $apexSupplier = $suppliers['SUP-APEX'];
        $globalSupplier = $suppliers['SUP-GLOBAL'];

        if (!Purchase::where('invoice_number', 'PUR-2026-0001')->exists()) {
            $purchase1 = Purchase::create([
                'invoice_number' => 'PUR-2026-0001',
                'supplier_id' => $apexSupplier->id,
                'supplier_name' => $apexSupplier->name,
                'purchase_date' => now()->subDays(18)->toDateString(),
                'status' => 'received',
                'total_amount' => 5300.00,
                'tax_amount' => 530.00,
                'grand_total' => 5830.00,
                'paid_amount' => 5830.00,
                'due_amount' => 0.00,
                'payment_status' => 'paid',
                'notes' => 'Q1 Electronics Restock for Central Logistics Hub',
                'is_active' => true,
            ]);

            PurchaseItem::create([
                'purchase_id' => $purchase1->id,
                'product_id' => $products['PRD-MON-4K']->id,
                'warehouse_id' => $centralWh->id,
                'quantity' => 15,
                'unit_price' => 240.00,
                'total_price' => 3600.00,
            ]);

            PurchaseItem::create([
                'purchase_id' => $purchase1->id,
                'product_id' => $products['PRD-MOU-MX']->id,
                'warehouse_id' => $centralWh->id,
                'quantity' => 25,
                'unit_price' => 58.00,
                'total_price' => 1450.00,
            ]);

            Payment::create([
                'payable_type' => Purchase::class,
                'payable_id' => $purchase1->id,
                'amount' => 5830.00,
                'payment_date' => now()->subDays(17)->toDateString(),
                'payment_method' => 'bank_transfer',
                'notes' => 'Wire transfer via Chase Commercial Banking',
            ]);
        }

        if (!Purchase::where('invoice_number', 'PUR-2026-0002')->exists()) {
            $purchase2 = Purchase::create([
                'invoice_number' => 'PUR-2026-0002',
                'supplier_id' => $globalSupplier->id,
                'supplier_name' => $globalSupplier->name,
                'purchase_date' => now()->subDays(8)->toDateString(),
                'status' => 'received',
                'total_amount' => 1890.00,
                'tax_amount' => 0.00,
                'grand_total' => 1890.00,
                'paid_amount' => 1000.00,
                'due_amount' => 890.00,
                'payment_status' => 'partial',
                'notes' => 'Stationery replenishment; balance payable net 30',
                'is_active' => true,
            ]);

            PurchaseItem::create([
                'purchase_id' => $purchase2->id,
                'product_id' => $products['PRD-PAP-A4']->id,
                'warehouse_id' => $centralWh->id,
                'quantity' => 300,
                'unit_price' => 4.20,
                'total_price' => 1260.00,
            ]);

            Payment::create([
                'payable_type' => Purchase::class,
                'payable_id' => $purchase2->id,
                'amount' => 1000.00,
                'payment_date' => now()->subDays(7)->toDateString(),
                'payment_method' => 'card',
                'notes' => 'Initial downpayment via Corporate Debit',
            ]);
        }

        // 9. Realistic Sales
        $nexusCustomer = $customers['CUST-NEXUS'];
        $clinicCustomer = $customers['CUST-CLINIC'];

        if (!Sale::where('invoice_number', 'INV-2026-0001')->exists()) {
            $sale1 = Sale::create([
                'invoice_number' => 'INV-2026-0001',
                'customer_id' => $nexusCustomer->id,
                'customer_name' => $nexusCustomer->name,
                'sale_date' => now()->subDays(10)->toDateString(),
                'total_amount' => 2449.92,
                'tax_amount' => 244.99,
                'grand_total' => 2694.91,
                'paid_amount' => 2694.91,
                'due_amount' => 0.00,
                'payment_status' => 'paid',
                'payment_method' => 'bank_transfer',
                'notes' => 'New workstation upgrade order for Nexus IT team',
                'is_active' => true,
            ]);

            SaleItem::create([
                'sale_id' => $sale1->id,
                'product_id' => $products['PRD-MON-4K']->id,
                'warehouse_id' => $centralWh->id,
                'quantity' => 4,
                'unit_price' => 349.99,
                'total_price' => 1399.96,
            ]);

            SaleItem::create([
                'sale_id' => $sale1->id,
                'product_id' => $products['PRD-MOU-MX']->id,
                'warehouse_id' => $centralWh->id,
                'quantity' => 6,
                'unit_price' => 89.99,
                'total_price' => 539.94,
            ]);

            SaleItem::create([
                'sale_id' => $sale1->id,
                'product_id' => $products['PRD-CHR-ERGO']->id,
                'warehouse_id' => $centralWh->id,
                'quantity' => 3,
                'unit_price' => 149.00,
                'total_price' => 447.00,
            ]);

            Payment::create([
                'payable_type' => Sale::class,
                'payable_id' => $sale1->id,
                'amount' => 2694.91,
                'payment_date' => now()->subDays(9)->toDateString(),
                'payment_method' => 'bank_transfer',
                'notes' => 'Full payment received via ACH',
            ]);
        }

        if (!Sale::where('invoice_number', 'INV-2026-0002')->exists()) {
            $sale2 = Sale::create([
                'invoice_number' => 'INV-2026-0002',
                'customer_id' => $clinicCustomer->id,
                'customer_name' => $clinicCustomer->name,
                'sale_date' => now()->subDays(4)->toDateString(),
                'total_amount' => 890.00,
                'tax_amount' => 89.00,
                'grand_total' => 979.00,
                'paid_amount' => 979.00,
                'due_amount' => 0.00,
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'notes' => 'Monthly clinical safety essentials',
                'is_active' => true,
            ]);

            SaleItem::create([
                'sale_id' => $sale2->id,
                'product_id' => $products['PRD-GLV-NIT']->id,
                'warehouse_id' => $centralWh->id,
                'quantity' => 50,
                'unit_price' => 11.50,
                'total_price' => 575.00,
            ]);

            SaleItem::create([
                'sale_id' => $sale2->id,
                'product_id' => $products['PRD-SAN-500']->id,
                'warehouse_id' => $centralWh->id,
                'quantity' => 60,
                'unit_price' => 4.99,
                'total_price' => 299.40,
            ]);

            Payment::create([
                'payable_type' => Sale::class,
                'payable_id' => $sale2->id,
                'amount' => 979.00,
                'payment_date' => now()->subDays(4)->toDateString(),
                'payment_method' => 'card',
                'notes' => 'Visa Card processed via POS terminal',
            ]);
        }

        // 10. Realistic Expenses
        $expensesData = [
            [
                'title' => 'Warehouse Facility Monthly Lease - March 2026',
                'category' => 'Operating Expenses',
                'amount' => 3800.00,
                'description' => 'Commercial warehouse lease for Chicago Central Logistics Hub',
                'created_by' => $admin->id,
            ],
            [
                'title' => 'High-Speed Commercial Fiber & Cloud Connectivity',
                'category' => 'Operating Expenses',
                'amount' => 420.00,
                'description' => 'Dedicated fiber line and warehouse Wi-Fi mesh systems',
                'created_by' => $manager->id,
            ],
            [
                'title' => 'Electric Pallet Jack & Forklift Annual Inspection',
                'category' => 'Maintenance Expenses',
                'amount' => 680.00,
                'description' => 'Mandatory OSHA safety certification and hydraulic fluid maintenance',
                'created_by' => $manager->id,
            ],
            [
                'title' => 'Barcode Thermal Label Printers & Scanners',
                'category' => 'Capital Expenses',
                'amount' => 1250.00,
                'description' => '4x Zebra 2D barcode scanners and industrial thermal label rolls',
                'created_by' => $admin->id,
            ],
        ];

        foreach ($expensesData as $exp) {
            Expense::firstOrCreate(
                ['title' => $exp['title']],
                $exp
            );
        }
    }
}
