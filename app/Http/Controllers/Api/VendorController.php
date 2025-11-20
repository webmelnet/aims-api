<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Vendor::query();
        
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $vendors = $request->has('paginate') && $request->paginate === 'false'
            ? $query->get()
            : $query->paginate($request->input('per_page', 15));

        return $request->has('paginate') && $request->paginate === 'false'
            ? $this->successResponse($vendors)
            : $this->paginatedResponse($vendors);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:vendors',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        $data = $request->all();
        
        // Convert boolean strings to actual booleans
        if (isset($data['is_active'])) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $vendor = Vendor::create($data);
        return $this->successResponse($vendor, 'Vendor created successfully', 201);
    }

    public function show($id)
    {
        $vendor = Vendor::with(['assets', 'maintenances'])->find($id);
        
        if (!$vendor) {
            return $this->errorResponse('Vendor not found', 404);
        }

        return $this->successResponse($vendor);
    }

    public function update(Request $request, $id)
    {
        $vendor = Vendor::find($id);
        
        if (!$vendor) {
            return $this->errorResponse('Vendor not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:vendors,code,' . $id,
            'email' => 'nullable|email',
            'contact_email' => 'nullable|email',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        $data = $request->all();
        
        // Convert boolean strings to actual booleans
        if (isset($data['is_active'])) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $vendor->update($data);
        return $this->successResponse($vendor, 'Vendor updated successfully');
    }

    public function destroy($id)
    {
        $vendor = Vendor::find($id);
        
        if (!$vendor) {
            return $this->errorResponse('Vendor not found', 404);
        }

        $vendor->delete();
        return $this->successResponse(null, 'Vendor deleted successfully');
    }
}