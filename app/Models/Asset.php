<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_tag',
        'name',
        'description',
        'category_id',
        'brand',
        'model',
        'serial_number',
        'barcode',
        'qr_code',
        'purchase_date',
        'purchase_cost',
        'vendor_id',
        'invoice_number',
        'purchase_order',
        'warranty_expiry_date',
        'warranty_months',
        'current_value',
        'salvage_value',
        'useful_life_years',
        'depreciation_method',
        'location_id',
        'department_id',
        'assigned_to',
        'assigned_at',
        'status',
        'condition',
        'specifications',
        'notes',
        'image',
        'is_critical',
        'next_maintenance_date',
        'disposal_date',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry_date' => 'date',
        'assigned_at' => 'datetime',
        'next_maintenance_date' => 'date',
        'disposal_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'current_value' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'is_critical' => 'boolean',
        'specifications' => 'array',
    ];

    /**
     * Get the category of the asset.
     */
    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    /**
     * Get the vendor of the asset.
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the location of the asset.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the department of the asset.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user to whom the asset is assigned.
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the assignment history of the asset.
     */
    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Get the current active assignment.
     */
    public function currentAssignment()
    {
        return $this->hasOne(AssetAssignment::class)->where('status', 'active')->latest();
    }

    /**
     * Get the maintenance records of the asset.
     */
    public function maintenances()
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    /**
     * Get the transfer history of the asset.
     */
    public function transfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    /**
     * Get the documents attached to the asset.
     */
    public function documents()
    {
        return $this->hasMany(AssetDocument::class);
    }

    /**
     * Get the checkout history of the asset.
     */
    public function checkouts()
    {
        return $this->hasMany(AssetCheckout::class);
    }

    /**
     * Get the current active checkout.
     */
    public function currentCheckout()
    {
        return $this->hasOne(AssetCheckout::class)->where('status', 'checked_out')->latest();
    }

    /**
     * Check if asset is available.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if asset is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->status === 'in_use' && $this->assigned_to !== null;
    }

    /**
     * Check if asset is under maintenance.
     */
    public function isUnderMaintenance(): bool
    {
        return in_array($this->status, ['maintenance', 'repair']);
    }

    /**
     * Calculate depreciation.
     */
    public function calculateDepreciation(): float
    {
        if (!$this->purchase_cost || !$this->purchase_date) {
            return 0;
        }

        $years = now()->diffInYears($this->purchase_date);
        
        if ($this->depreciation_method === 'straight-line' && $this->useful_life_years) {
            $annualDepreciation = ($this->purchase_cost - ($this->salvage_value ?? 0)) / $this->useful_life_years;
            return max(0, $this->purchase_cost - ($annualDepreciation * $years));
        }

        return $this->current_value ?? $this->purchase_cost;
    }
}
