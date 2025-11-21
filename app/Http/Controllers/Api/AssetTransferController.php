<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssetTransferController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of transfers.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $assetId = $request->input('asset_id');

        $query = AssetTransfer::with([
            'asset',
            'fromUser',
            'toUser',
            'fromLocation',
            'toLocation',
            'fromDepartment',
            'toDepartment',
            'transferredByUser',
            'approvedByUser'
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($assetId) {
            $query->where('asset_id', $assetId);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $transfers = $query->paginate($perPage);

        return $this->paginatedResponse($transfers, 'Transfers retrieved successfully');
    }

    /**
     * Initiate a new transfer.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'to_user_id' => 'nullable|exists:users,id',
            'to_location_id' => 'nullable|exists:locations,id',
            'to_department_id' => 'nullable|exists:departments,id',
            'transfer_date' => 'nullable|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
            'requires_approval' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $asset = Asset::find($request->asset_id);

            // Validate at least one destination is provided
            if (!$request->to_user_id && !$request->to_location_id && !$request->to_department_id) {
                return $this->errorResponse(
                    'At least one destination (user, location, or department) must be specified',
                    400
                );
            }

            $requiresApproval = $request->input('requires_approval', true);

            // Create transfer
            $transfer = AssetTransfer::create([
                'asset_id' => $request->asset_id,
                'from_user_id' => $asset->assigned_to,
                'from_location_id' => $asset->location_id,
                'from_department_id' => $asset->department_id,
                'to_user_id' => $request->to_user_id,
                'to_location_id' => $request->to_location_id,
                'to_department_id' => $request->to_department_id,
                'transferred_by' => auth()->id(),
                'transfer_date' => $request->transfer_date ?? now(),
                'reason' => $request->reason,
                'notes' => $request->notes,
                'status' => $requiresApproval ? 'pending' : 'completed',
                'approved_by' => !$requiresApproval ? auth()->id() : null,
                'approved_at' => !$requiresApproval ? now() : null,
            ]);

            // If no approval required, update asset immediately
            if (!$requiresApproval) {
                $this->applyTransfer($asset, $transfer);
            }

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_initiated',
                'model_type' => 'AssetTransfer',
                'model_id' => $transfer->id,
                'description' => "Initiated transfer for asset {$asset->name} ({$asset->asset_tag})",
                'new_values' => $transfer->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $transfer->load([
                    'asset',
                    'fromUser',
                    'toUser',
                    'fromLocation',
                    'toLocation',
                    'fromDepartment',
                    'toDepartment',
                    'transferredByUser'
                ]),
                'Transfer initiated successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to initiate transfer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified transfer.
     */
    public function show($id)
    {
        $transfer = AssetTransfer::with([
            'asset',
            'fromUser',
            'toUser',
            'fromLocation',
            'toLocation',
            'fromDepartment',
            'toDepartment',
            'transferredByUser',
            'approvedByUser'
        ])->find($id);

        if (!$transfer) {
            return $this->errorResponse('Transfer not found', 404);
        }

        return $this->successResponse($transfer, 'Transfer retrieved successfully');
    }

    /**
     * Approve a transfer.
     */
    public function approve(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $transfer = AssetTransfer::find($id);

            if (!$transfer) {
                return $this->errorResponse('Transfer not found', 404);
            }

            if ($transfer->status !== 'pending') {
                return $this->errorResponse('Transfer is not pending approval', 400);
            }

            $oldValues = $transfer->toArray();

            // Update transfer status
            $transfer->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Apply the transfer to the asset
            $this->applyTransfer($transfer->asset, $transfer);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_approved',
                'model_type' => 'AssetTransfer',
                'model_id' => $transfer->id,
                'description' => "Approved transfer for asset {$transfer->asset->name} ({$transfer->asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $transfer->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $transfer->load(['asset', 'toUser', 'toLocation', 'toDepartment']),
                'Transfer approved successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to approve transfer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject a transfer.
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $transfer = AssetTransfer::find($id);

            if (!$transfer) {
                return $this->errorResponse('Transfer not found', 404);
            }

            if ($transfer->status !== 'pending') {
                return $this->errorResponse('Transfer is not pending approval', 400);
            }

            $oldValues = $transfer->toArray();

            // Update transfer status
            $transfer->update([
                'status' => 'rejected',
                'notes' => ($transfer->notes ?? '') . "\n\nRejection reason: " . $request->rejection_reason,
            ]);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_rejected',
                'model_type' => 'AssetTransfer',
                'model_id' => $transfer->id,
                'description' => "Rejected transfer for asset {$transfer->asset->name} ({$transfer->asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $transfer->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse($transfer, 'Transfer rejected successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to reject transfer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel a transfer.
     */
    public function cancel(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $transfer = AssetTransfer::find($id);

            if (!$transfer) {
                return $this->errorResponse('Transfer not found', 404);
            }

            if ($transfer->status !== 'pending') {
                return $this->errorResponse('Only pending transfers can be cancelled', 400);
            }

            // Check if user is the one who initiated the transfer
            if ($transfer->transferred_by !== auth()->id()) {
                return $this->errorResponse('You can only cancel transfers you initiated', 403);
            }

            $oldValues = $transfer->toArray();

            $transfer->update(['status' => 'cancelled']);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'transfer_cancelled',
                'model_type' => 'AssetTransfer',
                'model_id' => $transfer->id,
                'description' => "Cancelled transfer for asset {$transfer->asset->name} ({$transfer->asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $transfer->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse($transfer, 'Transfer cancelled successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to cancel transfer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Apply transfer changes to asset.
     */
    private function applyTransfer(Asset $asset, AssetTransfer $transfer)
    {
        $updates = [];

        if ($transfer->to_user_id) {
            $updates['assigned_to'] = $transfer->to_user_id;
            $updates['assigned_at'] = now();
            $updates['status'] = 'in_use';
        }

        if ($transfer->to_location_id) {
            $updates['location_id'] = $transfer->to_location_id;
        }

        if ($transfer->to_department_id) {
            $updates['department_id'] = $transfer->to_department_id;
        }

        if (!empty($updates)) {
            $asset->update($updates);
        }

        $transfer->update(['status' => 'completed']);
    }

    /**
     * Get statistics for transfers.
     */
    public function statistics()
    {
        $stats = [
            'total' => AssetTransfer::count(),
            'pending' => AssetTransfer::where('status', 'pending')->count(),
            'approved' => AssetTransfer::where('status', 'approved')->count(),
            'completed' => AssetTransfer::where('status', 'completed')->count(),
            'rejected' => AssetTransfer::where('status', 'rejected')->count(),
            'cancelled' => AssetTransfer::where('status', 'cancelled')->count(),
            'recent_transfers' => AssetTransfer::with(['asset', 'fromUser', 'toUser'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return $this->successResponse($stats, 'Transfer statistics retrieved successfully');
    }
}
