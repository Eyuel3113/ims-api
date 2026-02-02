<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Customers
 * APIs for managing customers
 */
class CustomerController extends Controller
{
    /**
     * List Customers
     * 
     * Get paginated list of customers.
     * 
     * @queryParam search string optional Search by name, code, phone, email.
     * @queryParam status string optional filter by active/inactive.
     * @queryParam limit integer optional Default 10.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $limit = $request->query('limit', 10);

        $query = Customer::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate($limit);

        return response()->json([
            'message' => 'Customers fetched successfully',
            'data' => $customers->items(),
            'pagination' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
            ]
        ]);
    }

    /**
     * Create Customer
     * 
     * Add new customer.
     * 
     * @bodyParam name string required
     * @bodyParam code string required Unique
     * @bodyParam phone string optional
     * @bodyParam email string optional
     * @bodyParam address string optional
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:customers,code|max:50',
            'phone' => 'nullable|string|unique:customers,phone|max:20',
            'email' => 'nullable|email|unique:customers,email',
            'address' => 'nullable|string',
        ]);

        $customer = Customer::create($validated + ['is_active' => true]);

        return response()->json([
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }

    /**
     * Get Customer
     * 
     * Show single customer.
     * 
     * @urlParam id string required Customer UUID.
     */
    public function show($id)
    {
        $customer = Customer::with('sales')->findOrFail($id);

        return response()->json([
            'message' => 'Customer retrieved successfully',
            'data' => $customer
        ]);
    }

    /**
     * Update Customer
     * 
     * Update customer details.
     * 
     * @urlParam id string required Customer UUID.
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('customers', 'code')->ignore($id)],
            'phone' => ['nullable', 'string', Rule::unique('customers', 'phone')->ignore($id)],
            'email' => ['nullable', 'email', Rule::unique('customers', 'email')->ignore($id)],
            'address' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    /**
     * Delete Customer
     * 
     * Soft delete customer.
     * 
     * @urlParam id string required Customer UUID.
     */
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }

    /**
     * Toggle Customer Status
     */
    public function toggleStatus($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->is_active = !$customer->is_active;
        $customer->save();

        return response()->json([
            'message' => 'Customer visibility updated successfully',
            'is_active' => $customer->is_active,
            'data' => $customer
        ]);
    }

    /**
     * List Active Customers
     * @queryParam search string optional Search by name, code, phone, email.
     */
    public function activeCustomers(Request $request)
    {
        $search = $request->query('search');

        $query = Customer::active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Active customers fetched successfully',
            'data' => $customers
        ]);
    }
}
