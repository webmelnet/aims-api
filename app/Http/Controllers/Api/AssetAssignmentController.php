<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssetAssignmentController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of assignments.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $assetId = $request->input('asset_id');
        $userId = $request->input('user_id');

        $query = AssetAssignment::with([
            'asset',
            'assignedUser',
            'assignedByUser',
            'location',
            'department'
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($assetId) {
            $query->where('asset_id', $assetId);
        }

        if ($userId) {
            $query->where('assigned_to', $userId);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $assignments = $query->paginate($perPage);

        return $this->paginatedResponse($assignments, 'Assignments retrieved successfully');
    }

    /**
     * Assign an asset to a user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'assigned_to' => 'required|exists:users,id',
            'location_id' => 'nullable|exists:locations,id',
            'department_id' => 'nullable|exists:departments,id',
            'assigned_at' => 'nullable|date',
            'assignment_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $asset = Asset::find($request->asset_id);

            // Check if asset is available
            if (!$asset->isAvailable()) {
                return $this->errorResponse(
                    'Asset is not available for assignment. Current status: ' . $asset->status,
                    400
                );
            }

            // Close any existing active assignments
            AssetAssignment::where('asset_id', $asset->id)
                ->where('status', 'active')
                ->update(['status' => 'completed']);

            // Create new assignment
            $assignment = AssetAssignment::create([
                'asset_id' => $request->asset_id,
                'assigned_to' => $request->assigned_to,
                'assigned_by' => auth()->id(),
                'location_id' => $request->location_id,
                'department_id' => $request->department_id,
                'assigned_at' => $request->assigned_at ?? now(),
                'assignment_notes' => $request->assignment_notes,
                'status' => 'active',
            ]);

            // Update asset
            $asset->update([
                'assigned_to' => $request->assigned_to,
                'assigned_at' => $assignment->assigned_at,
                'status' => 'in_use',
                'location_id' => $request->location_id ?? $asset->location_id,
                'department_id' => $request->department_id ?? $asset->department_id,
            ]);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'assigned',
                'model_type' => 'AssetAssignment',
                'model_id' => $assignment->id,
                'description' => "Assigned asset {$asset->name} ({$asset->asset_tag}) to user",
                'new_values' => $assignment->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $assignment->load(['asset', 'assignedUser', 'assignedByUser']),
                'Asset assigned successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to assign asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified assignment.
     */
    public function show($id)
    {
        $assignment = AssetAssignment::with([
            'asset',
            'assignedUser',
            'assignedByUser',
            'location',
            'department'
        ])->find($id);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        return $this->successResponse($assignment, 'Assignment retrieved successfully');
    }

    /**
     * Return an asset (close assignment).
     */
    public function return(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'return_notes' => 'nullable|string',
            'return_condition' => 'required|in:excellent,good,fair,poor,damaged',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $assignment = AssetAssignment::find($id);

            if (!$assignment) {
                return $this->errorResponse('Assignment not found', 404);
            }

            if ($assignment->status !== 'active') {
                return $this->errorResponse('Assignment is not active', 400);
            }

            $oldValues = $assignment->toArray();

            // Update assignment
            $assignment->update([
                'returned_at' => now(),
                'return_notes' => $request->return_notes,
                'return_condition' => $request->return_condition,
                'status' => 'returned',
            ]);

            // Update asset
            $asset = $assignment->asset;
            $asset->update([
                'assigned_to' => null,
                'assigned_at' => null,
                'status' => 'available',
                'condition' => $request->return_condition,
            ]);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'returned',
                'model_type' => 'AssetAssignment',
                'model_id' => $assignment->id,
                'description' => "Returned asset {$asset->name} ({$asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $assignment->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $assignment->load(['asset', 'assignedUser']),
                'Asset returned successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to return asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get active assignments for a user.
     */
    public function userAssignments($userId)
    {
        $assignments = AssetAssignment::with(['asset', 'location', 'department'])
            ->where('assigned_to', $userId)
            ->where('status', 'active')
            ->orderBy('assigned_at', 'desc')
            ->get();

        return $this->successResponse($assignments, 'User assignments retrieved successfully');
    }

    /**
     * Get statistics for assignments.
     */
    public function statistics()
    {
        $stats = [
            'total' => AssetAssignment::count(),
            'active' => AssetAssignment::where('status', 'active')->count(),
            'returned' => AssetAssignment::where('status', 'returned')->count(),
            'by_department' => AssetAssignment::select('department_id', DB::raw('count(*) as count'))
                ->with('department:id,name')
                ->whereNotNull('department_id')
                ->where('status', 'active')
                ->groupBy('department_id')
                ->get(),
            'by_user' => AssetAssignment::select('assigned_to', DB::raw('count(*) as count'))
                ->with('assignedUser:id,name,email')
                ->where('status', 'active')
                ->groupBy('assigned_to')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ];

        return $this->successResponse($stats, 'Assignment statistics retrieved successfully');
    }
}
