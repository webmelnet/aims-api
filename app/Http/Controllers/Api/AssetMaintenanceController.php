<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssetMaintenanceController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of maintenance records.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $assetId = $request->input('asset_id');
        $maintenanceType = $request->input('maintenance_type');
        $priority = $request->input('priority');

        $query = AssetMaintenance::with([
            'asset',
            'performer',
            'vendor'
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($assetId) {
            $query->where('asset_id', $assetId);
        }

        if ($maintenanceType) {
            $query->where('maintenance_type', $maintenanceType);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        $sortBy = $request->input('sort_by', 'scheduled_date');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $maintenances = $query->paginate($perPage);

        return $this->paginatedResponse($maintenances, 'Maintenance records retrieved successfully');
    }

    /**
     * Schedule a new maintenance.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'maintenance_type' => 'required|in:preventive,corrective,predictive,routine,emergency',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'scheduled_date' => 'required|date',
            'cost' => 'nullable|numeric|min:0',
            'performed_by' => 'nullable|exists:users,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'priority' => 'required|in:low,medium,high,critical',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $asset = Asset::find($request->asset_id);

            // Create maintenance record
            $maintenance = AssetMaintenance::create([
                'asset_id' => $request->asset_id,
                'maintenance_type' => $request->maintenance_type,
                'title' => $request->title,
                'description' => $request->description,
                'scheduled_date' => $request->scheduled_date,
                'cost' => $request->cost,
                'performed_by' => $request->performed_by,
                'vendor_id' => $request->vendor_id,
                'priority' => $request->priority,
                'notes' => $request->notes,
                'status' => 'scheduled',
            ]);

            // Update asset next maintenance date
            if (!$asset->next_maintenance_date || $request->scheduled_date < $asset->next_maintenance_date) {
                $asset->update(['next_maintenance_date' => $request->scheduled_date]);
            }

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'maintenance_scheduled',
                'model_type' => 'AssetMaintenance',
                'model_id' => $maintenance->id,
                'description' => "Scheduled {$request->maintenance_type} maintenance for asset {$asset->name} ({$asset->asset_tag})",
                'new_values' => $maintenance->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $maintenance->load(['asset', 'performer', 'vendor']),
                'Maintenance scheduled successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to schedule maintenance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified maintenance record.
     */
    public function show($id)
    {
        $maintenance = AssetMaintenance::with([
            'asset',
            'performer',
            'vendor'
        ])->find($id);

        if (!$maintenance) {
            return $this->errorResponse('Maintenance record not found', 404);
        }

        return $this->successResponse($maintenance, 'Maintenance record retrieved successfully');
    }

    /**
     * Update the specified maintenance record.
     */
    public function update(Request $request, $id)
    {
        $maintenance = AssetMaintenance::find($id);

        if (!$maintenance) {
            return $this->errorResponse('Maintenance record not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'maintenance_type' => 'sometimes|in:preventive,corrective,predictive,routine,emergency',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'scheduled_date' => 'sometimes|date',
            'cost' => 'nullable|numeric|min:0',
            'performed_by' => 'nullable|exists:users,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $oldValues = $maintenance->toArray();

            $maintenance->update($request->all());

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'maintenance_updated',
                'model_type' => 'AssetMaintenance',
                'model_id' => $maintenance->id,
                'description' => "Updated maintenance record for asset {$maintenance->asset->name}",
                'old_values' => $oldValues,
                'new_values' => $maintenance->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $maintenance->load(['asset', 'performer', 'vendor']),
                'Maintenance record updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update maintenance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Start maintenance work.
     */
    public function start(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $maintenance = AssetMaintenance::find($id);

            if (!$maintenance) {
                return $this->errorResponse('Maintenance record not found', 404);
            }

            if ($maintenance->status !== 'scheduled') {
                return $this->errorResponse('Maintenance is not in scheduled status', 400);
            }

            $oldValues = $maintenance->toArray();

            $maintenance->update([
                'status' => 'in_progress',
                'performed_by' => $request->input('performed_by', auth()->id()),
            ]);

            // Update asset status
            $maintenance->asset->update(['status' => 'maintenance']);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'maintenance_started',
                'model_type' => 'AssetMaintenance',
                'model_id' => $maintenance->id,
                'description' => "Started maintenance for asset {$maintenance->asset->name} ({$maintenance->asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $maintenance->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $maintenance->load(['asset', 'performer']),
                'Maintenance started successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to start maintenance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Complete maintenance work.
     */
    public function complete(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'completed_date' => 'nullable|date',
            'cost' => 'nullable|numeric|min:0',
            'parts_replaced' => 'nullable|string',
            'downtime_hours' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'asset_condition' => 'required|in:excellent,good,fair,poor,damaged',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $maintenance = AssetMaintenance::find($id);

            if (!$maintenance) {
                return $this->errorResponse('Maintenance record not found', 404);
            }

            if (!in_array($maintenance->status, ['scheduled', 'in_progress'])) {
                return $this->errorResponse('Maintenance cannot be completed from current status', 400);
            }

            $oldValues = $maintenance->toArray();

            // Update maintenance record
            $maintenance->update([
                'status' => 'completed',
                'completed_date' => $request->input('completed_date', now()),
                'cost' => $request->cost ?? $maintenance->cost,
                'parts_replaced' => $request->parts_replaced,
                'downtime_hours' => $request->downtime_hours,
                'notes' => $maintenance->notes . "\n\n" . ($request->notes ?? ''),
            ]);

            // Update asset
            $maintenance->asset->update([
                'status' => 'available',
                'condition' => $request->asset_condition,
                'next_maintenance_date' => $this->calculateNextMaintenanceDate($maintenance),
            ]);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'maintenance_completed',
                'model_type' => 'AssetMaintenance',
                'model_id' => $maintenance->id,
                'description' => "Completed maintenance for asset {$maintenance->asset->name} ({$maintenance->asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $maintenance->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $maintenance->load(['asset', 'performer', 'vendor']),
                'Maintenance completed successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to complete maintenance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel maintenance.
     */
    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $maintenance = AssetMaintenance::find($id);

            if (!$maintenance) {
                return $this->errorResponse('Maintenance record not found', 404);
            }

            if (!in_array($maintenance->status, ['scheduled', 'in_progress'])) {
                return $this->errorResponse('Only scheduled or in-progress maintenance can be cancelled', 400);
            }

            $oldValues = $maintenance->toArray();

            $maintenance->update([
                'status' => 'cancelled',
                'notes' => $maintenance->notes . "\n\nCancellation reason: " . $request->cancellation_reason,
            ]);

            // Update asset status if it was in maintenance
            if ($maintenance->asset->status === 'maintenance') {
                $maintenance->asset->update(['status' => 'available']);
            }

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'maintenance_cancelled',
                'model_type' => 'AssetMaintenance',
                'model_id' => $maintenance->id,
                'description' => "Cancelled maintenance for asset {$maintenance->asset->name} ({$maintenance->asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $maintenance->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse($maintenance, 'Maintenance cancelled successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to cancel maintenance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get overdue maintenance records.
     */
    public function overdue()
    {
        $overdueMaintenances = AssetMaintenance::with(['asset', 'performer', 'vendor'])
            ->where('status', '!=', 'completed')
            ->where('scheduled_date', '<', now())
            ->orderBy('scheduled_date', 'asc')
            ->get();

        return $this->successResponse($overdueMaintenances, 'Overdue maintenance records retrieved successfully');
    }

    /**
     * Get upcoming maintenance records.
     */
    public function upcoming(Request $request)
    {
        $days = $request->input('days', 30);

        $upcomingMaintenances = AssetMaintenance::with(['asset', 'performer', 'vendor'])
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_date', [now(), now()->addDays($days)])
            ->orderBy('scheduled_date', 'asc')
            ->get();

        return $this->successResponse($upcomingMaintenances, 'Upcoming maintenance records retrieved successfully');
    }

    /**
     * Get maintenance statistics.
     */
    public function statistics()
    {
        $stats = [
            'total' => AssetMaintenance::count(),
            'scheduled' => AssetMaintenance::where('status', 'scheduled')->count(),
            'in_progress' => AssetMaintenance::where('status', 'in_progress')->count(),
            'completed' => AssetMaintenance::where('status', 'completed')->count(),
            'cancelled' => AssetMaintenance::where('status', 'cancelled')->count(),
            'overdue' => AssetMaintenance::where('status', '!=', 'completed')
                ->where('scheduled_date', '<', now())
                ->count(),
            'total_cost' => AssetMaintenance::where('status', 'completed')->sum('cost'),
            'by_type' => AssetMaintenance::select('maintenance_type', DB::raw('count(*) as count'))
                ->groupBy('maintenance_type')
                ->get(),
            'by_priority' => AssetMaintenance::select('priority', DB::raw('count(*) as count'))
                ->where('status', '!=', 'completed')
                ->groupBy('priority')
                ->get(),
        ];

        return $this->successResponse($stats, 'Maintenance statistics retrieved successfully');
    }

    /**
     * Calculate next maintenance date.
     */
    private function calculateNextMaintenanceDate(AssetMaintenance $maintenance): ?string
    {
        // For preventive maintenance, schedule next one based on type
        if ($maintenance->maintenance_type === 'preventive') {
            return now()->addMonths(3)->format('Y-m-d');
        }

        // For routine maintenance, schedule monthly
        if ($maintenance->maintenance_type === 'routine') {
            return now()->addMonth()->format('Y-m-d');
        }

        return null;
    }
}
