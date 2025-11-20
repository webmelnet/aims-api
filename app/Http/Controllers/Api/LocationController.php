<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Location::with(['parent', 'children']);
        
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $locations = $request->has('paginate') && $request->paginate === 'false'
            ? $query->get()
            : $query->paginate($request->input('per_page', 15));

        return $request->has('paginate') && $request->paginate === 'false'
            ? $this->successResponse($locations)
            : $this->paginatedResponse($locations);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:locations',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'parent_id' => 'nullable|exists:locations,id',
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

        $location = Location::create($data);
        return $this->successResponse($location, 'Location created successfully', 201);
    }

    public function show($id)
    {
        $location = Location::with(['parent', 'children', 'assets'])->find($id);
        
        if (!$location) {
            return $this->errorResponse('Location not found', 404);
        }

        return $this->successResponse($location);
    }

    public function update(Request $request, $id)
    {
        $location = Location::find($id);
        
        if (!$location) {
            return $this->errorResponse('Location not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:locations,code,' . $id,
            'parent_id' => 'nullable|exists:locations,id',
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

        $location->update($data);
        return $this->successResponse($location, 'Location updated successfully');
    }

    public function destroy($id)
    {
        $location = Location::find($id);
        
        if (!$location) {
            return $this->errorResponse('Location not found', 404);
        }

        $location->delete();
        return $this->successResponse(null, 'Location deleted successfully');
    }
}