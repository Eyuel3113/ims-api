<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Http\Controllers\Api\ReportsController;
use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new ReportsController();

function testAllReport($name, $method, $controller) {
    echo "Testing $name...\n";
    $request = new Request();
    $response = $controller->$method($request);
    $content = json_decode($response->getContent(), true);
    
    if (isset($content['data']) && !isset($content['pagination'])) {
        echo "SUCCESS: Found data object and NO pagination object.\n";
        echo "Count: " . count($content['data']) . "\n";
    } else {
        echo "FAILURE: Response format incorrect or pagination present.\n";
        print_r($content);
    }
    echo "---------------------------------\n";
}

echo "Starting verification for Non-Paginated Product Reports...\n\n";

testAllReport("All Product Sales Report", "productSalesReportAll", $controller);
testAllReport("All Product Purchase Report", "productPurchaseReportAll", $controller);

echo "\nVerification complete.\n";
