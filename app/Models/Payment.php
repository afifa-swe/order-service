<?php

namespace App\Models;

use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'status',
        'amount',
        'card_last4',
        'credit_provider',
        'credit_months',
        'monthly_payment',
    ];

    protected $casts = [
        'type' => PaymentType::class,
        'amount' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'credit_months' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
