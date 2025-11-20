<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssetCategoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = AssetCategory::with(['parent', 'children']);
        
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $categories = $request->has('paginate') && $request->paginate === 'false'
            ? $query->get()
            : $query->paginate($request->input('per_page', 15));

        return $request->has('paginate') && $request->paginate === 'false'
            ? $this->successResponse($categories)
            : $this->paginatedResponse($categories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:asset_categories',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:asset_categories,id',
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

        $category = AssetCategory::create($data);
        return $this->successResponse($category, 'Category created successfully', 201);
    }

    public function show($id)
    {
        $category = AssetCategory::with(['parent', 'children', 'assets'])->find($id);
        
        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        return $this->successResponse($category);
    }

    public function update(Request $request, $id)
    {
        $category = AssetCategory::find($id);
        
        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:asset_categories,code,' . $id,
            'parent_id' => 'nullable|exists:asset_categories,id',
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

        $category->update($data);
        return $this->successResponse($category, 'Category updated successfully');
    }

    public function destroy($id)
    {
        $category = AssetCategory::find($id);
        
        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        if ($category->assets()->count() > 0) {
            return $this->errorResponse('Cannot delete category with assets', 400);
        }

        $category->delete();
        return $this->successResponse(null, 'Category deleted successfully');
    }
}