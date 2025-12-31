<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

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
     * @queryParam category_id string optional Filter by category.
     * @queryParam has_expiry boolean optional 1 or 0
     * @queryParam low_stock boolean optional 1 = below min_stock
     * @queryParam limit integer optional Default 10.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');
        $hasExpiry = $request->query('has_expiry');
        $lowStock = $request->query('low_stock');
        $limit = $request->query('limit', 10);

        $query = Product::with(['category']);

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

        if ($hasExpiry !== null) {
            $query->where('has_expiry', $hasExpiry);
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
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('products', 'public');
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

        if ($product->photo) {
            $product->photo_url = asset('storage/' . $product->photo);
        }

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
        ]);

        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($product->photo) {
                Storage::disk('public')->delete($product->photo);
            }
            $validated['photo'] = $request->file('photo')->store('products', 'public');
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
}