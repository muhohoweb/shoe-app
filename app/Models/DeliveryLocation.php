<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryLocation extends Model
{
    protected $fillable = [
        'town',
        'delivery_fee',
        'is_active',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    // Scope to get only active locations (for dropdowns)
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('town');
    }
}