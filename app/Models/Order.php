<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'uuid',
        'customer_name',
        'mpesa_number',
        'mpesa_code',
        'amount',
        'payment_status',
        'tracking_number',
        'town',
        'description',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}