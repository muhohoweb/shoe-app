<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'merchant_request_id',
        'checkout_request_id',
        'phone_number',
        'amount',
        'account_reference',
        'mpesa_receipt_number',
        'result_code',
        'result_desc',
        'status',
        'callback_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'callback_data' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}