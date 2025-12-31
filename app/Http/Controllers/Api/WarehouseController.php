<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Warehouses
 * APIs for managing warehouses
 */
class WarehouseController extends Controller
{
    /**
     * List Warehouses
     * 
     * Get paginated list.
     * 
     * @queryParam search string optional Search by name or code.
     * @queryParam limit integer optional Default 10.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $limit = $request->query('limit', 10);

        $query = Warehouse::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $warehouses = $query->orderBy('name')->paginate($limit);

        return response()->json([
            'message' => 'Warehouses fetched successfully',
            'data' => $warehouses->items(),
            'pagination' => [
                'total' => $warehouses->total(),
                'per_page' => $warehouses->perPage(),
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
            ]
        ]);
    }

    /**
     * Create Warehouse
     * 
     * Add new warehouse.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:warehouses,code|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
        ]);

        $warehouse = Warehouse::create($validated + ['is_active' => true]);

        return response()->json([
            'message' => 'Warehouse created successfully',
            'data' => $warehouse
        ], 201);
    }

    /**
     * Get Warehouse
     */
    public function show($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        return response()->json([
            'message' => 'Warehouse retrieved successfully',
            'data' => $warehouse
        ]);
    }

    /**
     * Update Warehouse
     */
    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($id)],
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'data' => $warehouse
        ]);
    }

    /**
     * Delete Warehouse
     */
    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();

        return response()->json(['message' => 'Warehouse deleted successfully']);
    }
}