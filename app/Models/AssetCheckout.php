<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetCheckout extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'user_id',
        'checked_out_by',
        'checked_out_at',
        'expected_return_at',
        'checked_in_at',
        'checked_in_by',
        'checkout_notes',
        'checkin_notes',
        'condition_out',
        'condition_in',
        'status',
    ];

    protected $casts = [
        'checked_out_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    /**
     * Get the asset.
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the user who checked out the asset.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who processed the checkout.
     */
    public function checkedOutByUser()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    /**
     * Get the user who processed the check-in.
     */
    public function checkedInByUser()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * Check if checkout is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'checked_out' && 
               $this->expected_return_at && 
               $this->expected_return_at < now();
    }
}
