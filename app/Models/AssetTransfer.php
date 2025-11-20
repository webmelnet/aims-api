<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'from_user_id',
        'from_location_id',
        'from_department_id',
        'to_user_id',
        'to_location_id',
        'to_department_id',
        'transferred_by',
        'transfer_date',
        'reason',
        'notes',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'transfer_date' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the asset.
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the user from whom the asset is transferred.
     */
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * Get the user to whom the asset is transferred.
     */
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Get the location from which the asset is transferred.
     */
    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * Get the location to which the asset is transferred.
     */
    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * Get the department from which the asset is transferred.
     */
    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * Get the department to which the asset is transferred.
     */
    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /**
     * Get the user who initiated the transfer.
     */
    public function transferredByUser()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    /**
     * Get the user who approved the transfer.
     */
    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
