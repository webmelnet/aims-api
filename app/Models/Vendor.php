<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'contact_person',
        'contact_phone',
        'contact_email',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the assets purchased from this vendor.
     */
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get the maintenances performed by this vendor.
     */
    public function maintenances()
    {
        return $this->hasMany(AssetMaintenance::class);
    }
}
