<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCheckout;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of assets.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $status = $request->input('status');
        $categoryId = $request->input('category_id');
        $locationId = $request->input('location_id');
        $departmentId = $request->input('department_id');
        $assignedTo = $request->input('assigned_to');

        $query = Asset::with([
            'category',
            'location',
            'department',
            'assignedUser',
            'vendor'
        ]);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Category filter
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Location filter
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        // Department filter
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        // Assigned user filter
        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $assets = $query->paginate($perPage);

        return $this->paginatedResponse($assets, 'Assets retrieved successfully');
    }

    /**
     * Store a newly created asset.
     */
    public function store(Request $request)
    {
        // Convert boolean strings before validation
        $input = $request->all();
        if (isset($input['is_critical'])) {
            $input['is_critical'] = filter_var($input['is_critical'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $validator = Validator::make($input, [
            'asset_tag' => 'required|string|unique:assets',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'description' => 'nullable|string',
            'brand' => 'nullable|string',
            'model' => 'nullable|string',
            'serial_number' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric',
            'vendor_id' => 'nullable|exists:vendors,id',
            'warranty_expiry_date' => 'nullable|date',
            'location_id' => 'nullable|exists:locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'required|in:available,in_use,maintenance,repair,retired,disposed,lost,stolen',
            'condition' => 'required|in:excellent,good,fair,poor,damaged',
            'is_critical' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $data = $request->except(['image']);

            // Ensure boolean is properly set
            if (isset($input['is_critical'])) {
                $data['is_critical'] = $input['is_critical'];
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/assets'), $filename);
                $data['image'] = 'uploads/assets/' . $filename;
            }

            $asset = Asset::create($data);

            // Generate QR Code
            $qrCode = QrCode::format('png')
                ->size(300)
                ->generate($asset->asset_tag);

            $qrCodePath = 'qrcodes/' . $asset->asset_tag . '.png';
            Storage::disk('public')->put($qrCodePath, $qrCode);
            $asset->qr_code = $qrCodePath;
            $asset->save();

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'created',
                'model_type' => 'Asset',
                'model_id' => $asset->id,
                'description' => "Created asset: {$asset->name} ({$asset->asset_tag})",
                'new_values' => $asset->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $asset->load(['category', 'location', 'department', 'vendor']),
                'Asset created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified asset.
     */
    public function show($id)
    {
        $asset = Asset::with([
            'category',
            'location',
            'department',
            'assignedUser',
            'vendor',
            'assignments.assignedUser',
            'maintenances',
            'transfers',
            'documents',
            'checkouts.user'
        ])->find($id);

        if (!$asset) {
            return $this->errorResponse('Asset not found', 404);
        }

        return $this->successResponse($asset, 'Asset retrieved successfully');
    }

    /**
     * Update the specified asset.
     */
    public function update(Request $request, $id)
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return $this->errorResponse('Asset not found', 404);
        }

        // Convert boolean strings before validation
        $input = $request->all();
        if (isset($input['is_critical'])) {
            $input['is_critical'] = filter_var($input['is_critical'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $validator = Validator::make($input, [
            'asset_tag' => 'sometimes|string|unique:assets,asset_tag,' . $id,
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:asset_categories,id',
            'status' => 'sometimes|in:available,in_use,maintenance,repair,retired,disposed,lost,stolen',
            'condition' => 'sometimes|in:excellent,good,fair,poor,damaged',
            'is_critical' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $oldValues = $asset->toArray();
            $data = $request->except(['image']);

            // Ensure boolean is properly set
            if (isset($input['is_critical'])) {
                $data['is_critical'] = $input['is_critical'];
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($asset->image && file_exists(public_path($asset->image))) {
                    unlink(public_path($asset->image));
                }

                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/assets'), $filename);
                $data['image'] = 'uploads/assets/' . $filename;
            }

            $asset->update($data);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'updated',
                'model_type' => 'Asset',
                'model_id' => $asset->id,
                'description' => "Updated asset: {$asset->name} ({$asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $asset->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $asset->load(['category', 'location', 'department', 'vendor']),
                'Asset updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified asset.
     */
    public function destroy(Request $request, $id)
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return $this->errorResponse('Asset not found', 404);
        }

        try {
            DB::beginTransaction();

            $assetData = $asset->toArray();

            // Delete image if exists
            if ($asset->image && file_exists(public_path($asset->image))) {
                unlink(public_path($asset->image));
            }

            // Delete QR code if exists
            if ($asset->qr_code) {
                Storage::disk('public')->delete($asset->qr_code);
            }

            $asset->delete();

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'deleted',
                'model_type' => 'Asset',
                'model_id' => $id,
                'description' => "Deleted asset: {$assetData['name']} ({$assetData['asset_tag']})",
                'old_values' => $assetData,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(null, 'Asset deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to delete asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get asset history (assignments, transfers, maintenances).
     */
    public function history($id)
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return $this->errorResponse('Asset not found', 404);
        }

        $history = [
            'assignments' => $asset->assignments()->with(['assignedUser', 'assignedByUser', 'location', 'department'])->orderBy('created_at', 'desc')->get(),
            'transfers' => $asset->transfers()->with(['fromUser', 'toUser', 'transferredByUser', 'fromLocation', 'toLocation', 'fromDepartment', 'toDepartment'])->orderBy('created_at', 'desc')->get(),
            'maintenances' => $asset->maintenances()->with(['performer', 'vendor'])->orderBy('created_at', 'desc')->get(),
            'checkouts' => $asset->checkouts()->with(['user', 'checkedOutByUser', 'checkedInByUser'])->orderBy('created_at', 'desc')->get(),
        ];

        return $this->successResponse($history, 'Asset history retrieved successfully');
    }

    /**
     * Get QR code for asset.
     */
    public function getQrCode($id)
    {
        $asset = Asset::find($id);

        if (!$asset) {
            return $this->errorResponse('Asset not found', 404);
        }

        if (!$asset->qr_code || !Storage::disk('public')->exists($asset->qr_code)) {
            // Generate QR code if not exists
            $qrCode = QrCode::format('png')
                ->size(300)
                ->generate($asset->asset_tag);

            $qrCodePath = 'qrcodes/' . $asset->asset_tag . '.png';
            Storage::disk('public')->put($qrCodePath, $qrCode);
            $asset->qr_code = $qrCodePath;
            $asset->save();
        }

        return response()->file(storage_path('app/public/' . $asset->qr_code));
    }

    /**
     * Get asset statistics.
     */
    public function statistics()
    {
        $stats = [
            'total' => Asset::count(),
            'available' => Asset::where('status', 'available')->count(),
            'in_use' => Asset::where('status', 'in_use')->count(),
            'maintenance' => Asset::whereIn('status', ['maintenance', 'repair'])->count(),
            'retired' => Asset::where('status', 'retired')->count(),
            'by_category' => Asset::select('category_id', DB::raw('count(*) as count'))
                ->with('category:id,name')
                ->groupBy('category_id')
                ->get(),
            'by_location' => Asset::select('location_id', DB::raw('count(*) as count'))
                ->with('location:id,name')
                ->whereNotNull('location_id')
                ->groupBy('location_id')
                ->get(),
            'by_condition' => Asset::select('condition', DB::raw('count(*) as count'))
                ->groupBy('condition')
                ->get(),
            'total_value' => Asset::sum('current_value') ?? Asset::sum('purchase_cost'),
        ];

        return $this->successResponse($stats, 'Asset statistics retrieved successfully');
    }
}