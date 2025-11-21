<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $action = $request->input('action');
        $modelType = $request->input('model_type');
        $userId = $request->input('user_id');
        $modelId = $request->input('model_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = AuditLog::with(['user']);

        // Filter by action
        if ($action) {
            $query->where('action', $action);
        }

        // Filter by model type
        if ($modelType) {
            $query->where('model_type', $modelType);
        }

        // Filter by user
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Filter by model ID
        if ($modelId) {
            $query->where('model_id', $modelId);
        }

        // Filter by date range
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $logs = $query->paginate($perPage);

        return $this->paginatedResponse($logs, 'Audit logs retrieved successfully');
    }

    /**
     * Display the specified audit log.
     */
    public function show($id)
    {
        $log = AuditLog::with(['user'])->find($id);

        if (!$log) {
            return $this->errorResponse('Audit log not found', 404);
        }

        return $this->successResponse($log, 'Audit log retrieved successfully');
    }

    /**
     * Get audit logs for a specific model.
     */
    public function modelLogs(Request $request, $modelType, $modelId)
    {
        $perPage = $request->input('per_page', 15);

        $logs = AuditLog::with(['user'])
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->paginatedResponse($logs, 'Model audit logs retrieved successfully');
    }

    /**
     * Get audit logs for a specific user.
     */
    public function userLogs(Request $request, $userId)
    {
        $perPage = $request->input('per_page', 15);

        $logs = AuditLog::with(['user'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->paginatedResponse($logs, 'User audit logs retrieved successfully');
    }

    /**
     * Get recent activities.
     */
    public function recentActivities(Request $request)
    {
        $limit = $request->input('limit', 20);
        $modelType = $request->input('model_type');

        $query = AuditLog::with(['user']);

        if ($modelType) {
            $query->where('model_type', $modelType);
        }

        $activities = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->successResponse($activities, 'Recent activities retrieved successfully');
    }

    /**
     * Get audit log statistics.
     */
    public function statistics(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->subDays(30));
        $dateTo = $request->input('date_to', now());

        $stats = [
            'total' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'today' => AuditLog::whereDate('created_at', today())->count(),
            'this_week' => AuditLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => AuditLog::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'by_action' => AuditLog::select('action', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('action')
                ->orderByDesc('count')
                ->get(),
            'by_model_type' => AuditLog::select('model_type', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('model_type')
                ->orderByDesc('count')
                ->get(),
            'by_user' => AuditLog::select('user_id', DB::raw('count(*) as count'))
                ->with('user:id,name,email')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'daily_activity' => AuditLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get(),
        ];

        return $this->successResponse($stats, 'Audit log statistics retrieved successfully');
    }

    /**
     * Search audit logs.
     */
    public function search(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        if (!$search) {
            return $this->errorResponse('Search query is required', 400);
        }

        $query = AuditLog::with(['user'])
            ->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('model_type', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            })
            ->orderBy('created_at', 'desc');

        $logs = $query->paginate($perPage);

        return $this->paginatedResponse($logs, 'Search results retrieved successfully');
    }

    /**
     * Compare changes between old and new values.
     */
    public function compareChanges($id)
    {
        $log = AuditLog::find($id);

        if (!$log) {
            return $this->errorResponse('Audit log not found', 404);
        }

        if (!$log->old_values || !$log->new_values) {
            return $this->errorResponse('No values to compare', 400);
        }

        $changes = [];
        $oldValues = $log->old_values;
        $newValues = $log->new_values;

        // Find all changed fields
        foreach ($newValues as $key => $newValue) {
            if (!isset($oldValues[$key]) || $oldValues[$key] !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValues[$key] ?? null,
                    'new' => $newValue,
                ];
            }
        }

        // Check for removed fields
        foreach ($oldValues as $key => $oldValue) {
            if (!isset($newValues[$key])) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => null,
                ];
            }
        }

        return $this->successResponse([
            'log' => $log,
            'changes' => $changes,
        ], 'Changes compared successfully');
    }

    /**
     * Get activity timeline for a model.
     */
    public function timeline($modelType, $modelId)
    {
        $timeline = AuditLog::with(['user'])
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy(function ($log) {
                return $log->created_at->format('Y-m-d');
            });

        return $this->successResponse($timeline, 'Timeline retrieved successfully');
    }

    /**
     * Export audit logs.
     */
    public function export(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->subDays(30));
        $dateTo = $request->input('date_to', now());
        $modelType = $request->input('model_type');
        $action = $request->input('action');

        $query = AuditLog::with(['user'])
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        if ($modelType) {
            $query->where('model_type', $modelType);
        }

        if ($action) {
            $query->where('action', $action);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        // Transform for export
        $exportData = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'user' => $log->user ? $log->user->name : 'System',
                'action' => $log->action,
                'model_type' => $log->model_type,
                'model_id' => $log->model_id,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->successResponse($exportData, 'Audit logs exported successfully');
    }
}
