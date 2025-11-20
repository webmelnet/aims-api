<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Department::with(['manager', 'users']);
        
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $departments = $request->has('paginate') && $request->paginate === 'false'
            ? $query->get()
            : $query->paginate($request->input('per_page', 15));

        return $request->has('paginate') && $request->paginate === 'false'
            ? $this->successResponse($departments)
            : $this->paginatedResponse($departments);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:departments',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
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

        $department = Department::create($data);
        return $this->successResponse($department, 'Department created successfully', 201);
    }

    public function show($id)
    {
        $department = Department::with(['manager', 'users', 'assets'])->find($id);
        
        if (!$department) {
            return $this->errorResponse('Department not found', 404);
        }

        return $this->successResponse($department);
    }

    public function update(Request $request, $id)
    {
        $department = Department::find($id);
        
        if (!$department) {
            return $this->errorResponse('Department not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:departments,code,' . $id,
            'manager_id' => 'nullable|exists:users,id',
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

        $department->update($data);
        return $this->successResponse($department, 'Department updated successfully');
    }

    public function destroy($id)
    {
        $department = Department::find($id);
        
        if (!$department) {
            return $this->errorResponse('Department not found', 404);
        }

        $department->delete();
        return $this->successResponse(null, 'Department deleted successfully');
    }
}