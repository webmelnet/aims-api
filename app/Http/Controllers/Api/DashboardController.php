<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetCheckout;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function statistics()
    {
        $stats = [
            'assets' => [
                'total' => Asset::count(),
                'available' => Asset::where('status', 'available')->count(),
                'in_use' => Asset::where('status', 'in_use')->count(),
                'maintenance' => Asset::whereIn('status', ['maintenance', 'repair'])->count(),
                'retired' => Asset::where('status', 'retired')->count(),
            ],
            'maintenances' => [
                'scheduled' => AssetMaintenance::where('status', 'scheduled')->count(),
                'in_progress' => AssetMaintenance::where('status', 'in_progress')->count(),
                'overdue' => AssetMaintenance::where('status', 'overdue')->count(),
            ],
            'checkouts' => [
                'active' => AssetCheckout::where('status', 'checked_out')->count(),
                'overdue' => AssetCheckout::where('status', 'overdue')->count(),
            ],
            'total_value' => Asset::sum('current_value') ?? Asset::sum('purchase_cost'),
        ];

        return $this->successResponse($stats);
    }

    public function recentActivities()
    {
        $activities = AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return $this->successResponse($activities);
    }

    public function alerts()
    {
        $alerts = [
            'warranty_expiring' => Asset::whereBetween('warranty_expiry_date', [
                now(),
                now()->addDays(30)
            ])->count(),
            'maintenance_due' => AssetMaintenance::where('status', 'scheduled')
                ->where('scheduled_date', '<=', now()->addDays(7))
                ->count(),
            'overdue_checkouts' => AssetCheckout::where('status', 'overdue')->count(),
        ];

        return $this->successResponse($alerts);
    }
}