<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{

    protected $fillable = ['name', 'gender', 'total_price', 'note', 'payment_method_id', 'paidAmount', 'changeAmount'];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class, 'order_id');
    }

}
