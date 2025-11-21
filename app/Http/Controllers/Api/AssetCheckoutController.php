<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCheckout;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssetCheckoutController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of checkouts.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $assetId = $request->input('asset_id');
        $userId = $request->input('user_id');

        $query = AssetCheckout::with([
            'asset',
            'user',
            'checkedOutByUser',
            'checkedInByUser'
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($assetId) {
            $query->where('asset_id', $assetId);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $sortBy = $request->input('sort_by', 'checked_out_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $checkouts = $query->paginate($perPage);

        return $this->paginatedResponse($checkouts, 'Checkouts retrieved successfully');
    }

    /**
     * Checkout an asset.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'user_id' => 'required|exists:users,id',
            'expected_return_at' => 'nullable|date|after:now',
            'checkout_notes' => 'nullable|string',
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
                    'Asset is not available for checkout. Current status: ' . $asset->status,
                    400
                );
            }

            // Check if asset is already checked out
            $existingCheckout = AssetCheckout::where('asset_id', $asset->id)
                ->where('status', 'checked_out')
                ->first();

            if ($existingCheckout) {
                return $this->errorResponse('Asset is already checked out', 400);
            }

            // Create checkout record
            $checkout = AssetCheckout::create([
                'asset_id' => $request->asset_id,
                'user_id' => $request->user_id,
                'checked_out_by' => auth()->id(),
                'checked_out_at' => now(),
                'expected_return_at' => $request->expected_return_at,
                'checkout_notes' => $request->checkout_notes,
                'condition_out' => $asset->condition,
                'status' => 'checked_out',
            ]);

            // Update asset status
            $asset->update([
                'status' => 'in_use',
            ]);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'checked_out',
                'model_type' => 'AssetCheckout',
                'model_id' => $checkout->id,
                'description' => "Checked out asset {$asset->name} ({$asset->asset_tag}) to user",
                'new_values' => $checkout->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $checkout->load(['asset', 'user', 'checkedOutByUser']),
                'Asset checked out successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to checkout asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified checkout.
     */
    public function show($id)
    {
        $checkout = AssetCheckout::with([
            'asset',
            'user',
            'checkedOutByUser',
            'checkedInByUser'
        ])->find($id);

        if (!$checkout) {
            return $this->errorResponse('Checkout not found', 404);
        }

        return $this->successResponse($checkout, 'Checkout retrieved successfully');
    }

    /**
     * Check in an asset.
     */
    public function checkin(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'checkin_notes' => 'nullable|string',
            'condition_in' => 'required|in:excellent,good,fair,poor,damaged',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $checkout = AssetCheckout::find($id);

            if (!$checkout) {
                return $this->errorResponse('Checkout not found', 404);
            }

            if ($checkout->status !== 'checked_out') {
                return $this->errorResponse('Asset is not currently checked out', 400);
            }

            $oldValues = $checkout->toArray();

            // Update checkout record
            $checkout->update([
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
                'checkin_notes' => $request->checkin_notes,
                'condition_in' => $request->condition_in,
                'status' => 'checked_in',
            ]);

            // Update asset
            $asset = $checkout->asset;
            $asset->update([
                'status' => 'available',
                'condition' => $request->condition_in,
            ]);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'checked_in',
                'model_type' => 'AssetCheckout',
                'model_id' => $checkout->id,
                'description' => "Checked in asset {$asset->name} ({$asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $checkout->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $checkout->load(['asset', 'user', 'checkedInByUser']),
                'Asset checked in successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to checkin asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Extend checkout due date.
     */
    public function extend(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'expected_return_at' => 'required|date|after:now',
            'extension_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $checkout = AssetCheckout::find($id);

            if (!$checkout) {
                return $this->errorResponse('Checkout not found', 404);
            }

            if ($checkout->status !== 'checked_out') {
                return $this->errorResponse('Only active checkouts can be extended', 400);
            }

            $oldValues = $checkout->toArray();

            $notes = $checkout->checkout_notes ?? '';
            if ($request->extension_reason) {
                $notes .= "\n\nExtended on " . now()->format('Y-m-d H:i:s') . ": " . $request->extension_reason;
            }

            $checkout->update([
                'expected_return_at' => $request->expected_return_at,
                'checkout_notes' => $notes,
            ]);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'checkout_extended',
                'model_type' => 'AssetCheckout',
                'model_id' => $checkout->id,
                'description' => "Extended checkout for asset {$checkout->asset->name} ({$checkout->asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $checkout->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $checkout->load(['asset', 'user']),
                'Checkout extended successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to extend checkout: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get active checkouts for a user.
     */
    public function userCheckouts($userId)
    {
        $checkouts = AssetCheckout::with(['asset'])
            ->where('user_id', $userId)
            ->where('status', 'checked_out')
            ->orderBy('checked_out_at', 'desc')
            ->get();

        return $this->successResponse($checkouts, 'User checkouts retrieved successfully');
    }

    /**
     * Get overdue checkouts.
     */
    public function overdue()
    {
        $overdueCheckouts = AssetCheckout::with(['asset', 'user', 'checkedOutByUser'])
            ->where('status', 'checked_out')
            ->where('expected_return_at', '<', now())
            ->whereNotNull('expected_return_at')
            ->orderBy('expected_return_at', 'asc')
            ->get();

        return $this->successResponse($overdueCheckouts, 'Overdue checkouts retrieved successfully');
    }

    /**
     * Get checkout statistics.
     */
    public function statistics()
    {
        $stats = [
            'total' => AssetCheckout::count(),
            'active' => AssetCheckout::where('status', 'checked_out')->count(),
            'completed' => AssetCheckout::where('status', 'checked_in')->count(),
            'overdue' => AssetCheckout::where('status', 'checked_out')
                ->where('expected_return_at', '<', now())
                ->whereNotNull('expected_return_at')
                ->count(),
            'today_checkouts' => AssetCheckout::whereDate('checked_out_at', today())->count(),
            'today_checkins' => AssetCheckout::whereDate('checked_in_at', today())->count(),
            'by_user' => AssetCheckout::select('user_id', DB::raw('count(*) as count'))
                ->with('user:id,name,email')
                ->where('status', 'checked_out')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'most_checked_out_assets' => AssetCheckout::select('asset_id', DB::raw('count(*) as count'))
                ->with('asset:id,asset_tag,name')
                ->groupBy('asset_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ];

        return $this->successResponse($stats, 'Checkout statistics retrieved successfully');
    }

    /**
     * Report lost or damaged asset during checkout.
     */
    public function reportIssue(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'issue_type' => 'required|in:lost,stolen,damaged',
            'description' => 'required|string',
            'condition_in' => 'required_if:issue_type,damaged|in:excellent,good,fair,poor,damaged',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $checkout = AssetCheckout::find($id);

            if (!$checkout) {
                return $this->errorResponse('Checkout not found', 404);
            }

            if ($checkout->status !== 'checked_out') {
                return $this->errorResponse('Can only report issues for active checkouts', 400);
            }

            $oldValues = $checkout->toArray();

            // Update checkout record
            $checkout->update([
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
                'checkin_notes' => "ISSUE REPORTED: " . strtoupper($request->issue_type) . "\n" . $request->description,
                'condition_in' => $request->condition_in ?? 'damaged',
                'status' => 'checked_in',
            ]);

            // Update asset status based on issue type
            $asset = $checkout->asset;
            $assetStatus = match ($request->issue_type) {
                'lost' => 'lost',
                'stolen' => 'stolen',
                'damaged' => 'repair',
            };

            $asset->update([
                'status' => $assetStatus,
                'condition' => $request->condition_in ?? 'damaged',
            ]);

            // Log activity
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'issue_reported',
                'model_type' => 'AssetCheckout',
                'model_id' => $checkout->id,
                'description' => "Reported {$request->issue_type} for asset {$asset->name} ({$asset->asset_tag})",
                'old_values' => $oldValues,
                'new_values' => $checkout->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return $this->successResponse(
                $checkout->load(['asset', 'user']),
                'Issue reported successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to report issue: ' . $e->getMessage(), 500);
        }
    }
}
