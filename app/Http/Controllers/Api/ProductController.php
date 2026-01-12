<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * @group Products
 * APIs for managing products with barcode, expiry, photo, min stock
 */
class ProductController extends Controller
{
    /**
     * List Products
     * 
     * Get paginated list of products with filters.
     * 
     * @queryParam search string optional Search by name, code, barcode.
     * @queryParam status string optional filter by active/inactive.
     * @queryParam category_id string optional Filter by category.
     * queryParam barcode string optional filter by barcode.
     * @queryParam has_expiry boolean optional 1 or 0
     * @queryParam low_stock boolean optional 1 = below min_stock
     * @queryParam limit integer optional Default 10.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');
        $barcode = $request->query('barcode');
        $hasExpiry = $request->query('has_expiry');
        $lowStock = $request->query('low_stock');
        $status = $request->query('status');
        $limit = $request->query('limit', 10);

        $query = Product::with(['category'])->withSum('stocks', 'quantity');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if($barcode){
            $query->where('barcode',$barcode);
        }

        if ($hasExpiry !== null) {
            $query->where('has_expiry', $hasExpiry);
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($lowStock == 1) {
            $query->whereRaw('min_stock > (SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE stocks.product_id = products.id)');
        }

        $products = $query->orderBy('name')->paginate($limit);

        return response()->json([
            'message' => 'Products fetched successfully',
            'data' => $products->items(),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }


    /**
 * Search Product by Barcode
 * 
 * Scan barcode to get product details — for POS/sales.
 * 
 * @queryParam barcode string required The scanned barcode.
 */
public function searchByBarcode(Request $request)
{
    $request->validate([
        'barcode' => 'required|string|max:100',
    ]);

    $product = Product::with('category')
        ->where('barcode', $request->barcode)
        ->active()
        ->first();

    if (!$product) {
        return response()->json([
            'message' => 'Product not found with this barcode',
        ], 404);
    }

    return response()->json([
        'message' => 'Product found',
        'data' => $product
    ]);
}


    /**
 * Generate EAN-13 style barcode
 * Format: 13 digits — first 12 random, last is check digit
 */
private function generateBarcode(): string
{
    // Generate 12 random digits
    $number = mt_rand(100000000000, 999999999999);

    // Calculate check digit
    $sum = 0;
    $digits = str_split($number);
    for ($i = 0; $i < 12; $i++) {
        $sum += ($i % 2 === 0) ? $digits[$i] : $digits[$i] * 3;
    }
    $checkDigit = (10 - ($sum % 10)) % 10;

    return $number . $checkDigit;
}


/**
 * Get Barcode Image
 * 
 * Generate and display barcode SVG image for printing.
 * 
 * @urlParam id string required Product UUID
 * @queryParam size string optional small, medium, large (default medium)
 * @queryParam download boolean optional 1 = force download
 */
public function barcodeImage($id, Request $request)
{
    $product = Product::findOrFail($id);

    if (!$product->barcode) {
        return response()->json(['message' => 'Product has no barcode'], 404);
    }

    $size = $request->query('size', 'medium');
    $download = $request->query('download') == '1';

    $sizes = [
        'small' => 0.8,
        'medium' => 1.0,
        'large' => 1.5,
    ];

    $magnification = $sizes[$size] ?? 1.0;

    $svg = $this->generateBarcodeSvg($product->barcode, $magnification);

    if ($download) {
        return response($svg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="barcode_' . $product->code . '.svg"');
    }

    return response($svg)->header('Content-Type', 'image/svg+xml');
}


private function generateBarcodeSvg(string $barcode, float $magnification = 1.0): string
{
    // EAN-13 encoding patterns
    $patternA = [
        '0' => '0001101', '1' => '0011001', '2' => '0010011', '3' => '0111101',
        '4' => '0100011', '5' => '0110001', '6' => '0101111', '7' => '0111011',
        '8' => '0110111', '9' => '0001011'
    ];
    $patternB = [
        '0' => '0100111', '1' => '0110011', '2' => '0011011', '3' => '0100001',
        '4' => '0011101', '5' => '0111001', '6' => '0000101', '7' => '0010001',
        '8' => '0001001', '9' => '0010111'
    ];
    $patternC = [
        '0' => '1110010', '1' => '1100110', '2' => '1101100', '3' => '1000010',
        '4' => '1011100', '5' => '1001110', '6' => '1010000', '7' => '1000100',
        '8' => '1001000', '9' => '1110100'
    ];

    $start = '101';
    $middle = '01010';
    $end = '101';

    $digits = str_split($barcode);
    $first = $digits[0];
    $leftParity = [
        '0' => ['A','A','A','A','A','A'], '1' => ['A','A','B','A','B','B'],
        '2' => ['A','A','B','B','A','B'], '3' => ['A','A','B','B','B','A'],
        '4' => ['A','B','A','A','B','B'], '5' => ['A','B','B','A','A','B'],
        '6' => ['A','B','B','B','A','A'], '7' => ['A','B','A','B','A','B'],
        '8' => ['A','B','A','B','B','A'], '9' => ['A','B','B','A','B','A']
    ];
    $parity = $leftParity[$first];

    $encoded = [];
    foreach(str_split($start) as $b) $encoded[] = ['b' => $b, 'g' => true];
    for ($i = 1; $i <= 6; $i++) {
        $p = $parity[$i-1] === 'A' ? $patternA : $patternB;
        foreach(str_split($p[$digits[$i]]) as $b) $encoded[] = ['b' => $b, 'g' => false];
    }
    foreach(str_split($middle) as $b) $encoded[] = ['b' => $b, 'g' => true];
    for ($i = 7; $i <= 12; $i++) {
        foreach(str_split($patternC[$digits[$i]]) as $b) $encoded[] = ['b' => $b, 'g' => false];
    }
    foreach(str_split($end) as $b) $encoded[] = ['b' => $b, 'g' => true];

    // Standard EAN-13 Dimensions in mm
    $moduleWidth = 0.33 * $magnification;
    $quietZone = 3.63 * $magnification; // approx 11 modules
    $barHeight = 22.85 * $magnification;
    $guardHeight = $barHeight + (1.65 * $magnification);
    $totalWidth = (count($encoded) * $moduleWidth) + (2 * $quietZone);
    $totalHeight = $guardHeight + (3 * $magnification); // Extra for text space

    $svg = '<svg width="' . $totalWidth . 'mm" height="' . $totalHeight . 'mm" viewBox="0 0 ' . $totalWidth . ' ' . $totalHeight . '" xmlns="http://www.w3.org/2000/svg">';
    $svg .= '<rect width="100%" height="100%" fill="white"/>';

    $x = $quietZone;
    foreach ($encoded as $item) {
        if ($item['b'] == '1') {
            $h = $item['g'] ? $guardHeight : $barHeight;
            $svg .= '<rect x="' . $x . '" y="0" width="' . $moduleWidth . '" height="' . $h . '" fill="black"/>';
        }
        $x += $moduleWidth;
    }

    $fontSize = 3 * $magnification;
    $textY = $guardHeight + $fontSize;
    
    $svg .= '<text x="' . ($quietZone / 2) . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" text-anchor="middle">' . $digits[0] . '</text>';
    
    $leftGroupX = $quietZone + (3 + 10.5) * $moduleWidth;
    $svg .= '<text x="' . $leftGroupX . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" text-anchor="middle">' . substr($barcode, 1, 6) . '</text>';
    
    $rightGroupX = $quietZone + (3 + 42 + 5 + 10.5) * $moduleWidth;
    $svg .= '<text x="' . $rightGroupX . '" y="' . $textY . '" font-family="Arial, sans-serif" font-size="' . $fontSize . '" text-anchor="middle">' . substr($barcode, 7, 6) . '</text>';

    $svg .= '</svg>';

    return $svg;
}

    /**
     * Create Product
     * 
     * Add new product with photo upload.
     * 
     * @bodyParam name string required
     * @bodyParam code string required Unique
     * @bodyParam category_id string required Category UUID
     * @bodyParam unit string required e.g., pcs, kg
     * @bodyParam barcode string optional Unique
     * @bodyParam photo file optional Image file
     * @bodyParam min_stock integer optional Default 0
     * @bodyParam has_expiry boolean optional Default false
     * @bodyParam purchase_price number required
     * @bodyParam selling_price number required
     * @bodyParam is_active boolean optional Default true
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:products,code|max:50',
            'category_id' => 'required|exists:categories,id',
            'unit' => 'required|string|max:20',
            'barcode' => 'nullable|string|unique:products,barcode|max:100',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'min_stock' => 'nullable|integer|min:0',
            'has_expiry' => 'nullable|boolean',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'is_vatable' => 'nullable|boolean',
        ]);

        if (empty($validated['barcode'])) {
        do {
            $validated['barcode'] = $this->generateBarcode();
        } while (Product::where('barcode', $validated['barcode'])->exists());
    }

        if ($request->hasFile('photo')) {
            $validated['photo'] = $this->storePhoto($request);
        }

        $product = Product::create($validated + ['is_active' => true]);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product->load('category')
        ], 201);
    }

    /**
     * Get Product
     * 
     * Show single product with photo URL.
     * 
     * @urlParam id string required Product UUID.
     */
    public function show($id)
    {
        $product = Product::with('category')->findOrFail($id);

        return response()->json([
            'message' => 'Product retrieved successfully',
            'data' => $product
        ]);
    }

    /**
     * Update Product
     * 
     * Update product details and photo.
     * 
     * @urlParam id string required Product UUID.
     *
     * @bodyParam name string optional
     * @bodyParam code string optional
     * @bodyParam category_id string optional
     * @bodyParam unit string optional
     * @bodyParam barcode string optional
     * @bodyParam photo file optional
     * @bodyParam min_stock integer optional
     * @bodyParam has_expiry boolean optional
     * @bodyParam purchase_price number optional
     * @bodyParam selling_price number optional
     * @bodyParam is_active boolean optional
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('products', 'code')->ignore($id)],
            'category_id' => 'sometimes|exists:categories,id',
            'unit' => 'sometimes|string|max:20',
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($id)],
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'min_stock' => 'nullable|integer|min:0',
            'has_expiry' => 'nullable|boolean',
            'is_active' => 'sometimes|boolean',
            'is_vatable' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $this->storePhoto($request, $product);
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product->load('category')
        ]);
    }

    /**
     * Delete Product
     * 
     * Soft delete product.
     * 
     * @urlParam id string required Product UUID.
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Toggle Product Status
     */
    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'message' => 'Product visibility updated successfully',
            'is_active' => $product->is_active,
            'data' => $product
        ]);
    }

    /**
     * List Active Products
     * @queryParam search string optional Search by name, code, barcode.
     * queryParam barcode string optional filter by barcode.
     */
    public function activeProducts(Request $request)
    {
        $search = $request->query('search');

        $query = Product::active()->with('category');
        $barcode = $request->query('barcode');


        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }
        if($barcode){
            $query->where('barcode',$barcode);
        }
        $products = $query->orderBy('name')->get();

        return response()->json([
            'message' => 'Active products fetched successfully',
            'data' => $products
        ]);
    }

    /**
     * Upload Product Photo
     */
    public function uploadPhoto(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $path = $this->storePhoto($request, $product);

        $product->update(['photo' => $path]);

        return response()->json([
            'message' => 'Photo uploaded successfully',
            'photo_url' => $product->photo_url,
            'data' => $product
        ]);
    }

    /**
     * Delete Product Photo
     */
    public function deletePhoto($id)
    {
        $product = Product::findOrFail($id);

        if ($product->photo) {
            Storage::disk('public')->delete($product->photo);
            $product->update(['photo' => null]);
        }

        return response()->json([
            'message' => 'Photo deleted successfully',
            'photo_url' => $product->photo_url,
            'data' => $product
        ]);
    }

    /**
     * Store and process photo (WebP conversion)
     */
    private function storePhoto(Request $request, Product $product = null)
    {
        if (!$request->hasFile('photo')) {
            return null;
        }

        // Delete old photo if modifying
        if ($product && $product->photo) {
            Storage::disk('public')->delete($product->photo);
        }

        $file = $request->file('photo');
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '_' . time() . '.webp';
        $path = 'products/' . $filename;

        // Ensure directory exists
        if (!Storage::disk('public')->exists('products')) {
            Storage::disk('public')->makeDirectory('products');
        }

        // Convert to WebP using Intervention Image v3
        $image = Image::read($file);
        $encoded = $image->toWebp(80);
        
        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }
}
