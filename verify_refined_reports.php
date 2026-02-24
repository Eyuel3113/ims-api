<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Http\Controllers\Api\ReportsController;
use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new ReportsController();

function testRefinedReport($name, $method, $controller, $params = []) {
    echo "Testing $name...\n";
    $request = new Request($params);
    $response = $controller->$method($request);
    $content = json_decode($response->getContent(), true);
    
    if (isset($content['pagination']) && isset($content['data'])) {
        echo "SUCCESS: Found pagination and data objects.\n";
        echo "Pagination: " . json_encode($content['pagination']) . "\n";
        if (count($content['data']) > 0) {
            $item = $content['data'][0];
            echo "Product Details: " . $item['name'] . " | Category: " . ($item['category_name'] ?? 'N/A') . " | Unit: " . ($item['unit'] ?? 'N/A') . "\n";
        }
    } else {
        echo "FAILURE: Response format incorrect.\n";
        print_r($content);
    }
    echo "---------------------------------\n";
}

echo "Starting verification for Refined Product Reports...\n\n";

testRefinedReport("Refined Product Sales Report", "productSalesReport", $controller);
testRefinedReport("Refined Product Purchase Report", "productPurchaseReport", $controller);

echo "\nVerification complete.\n";
