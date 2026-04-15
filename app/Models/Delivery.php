<?php

namespace App\Models;

use App\Enums\DeliveryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'pickup_point_id',
        'city',
        'street',
        'house',
        'apartment',
        'cost',
    ];

    protected $casts = [
        'type' => DeliveryType::class,
        'cost' => 'decimal:2',
        'pickup_point_id' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
