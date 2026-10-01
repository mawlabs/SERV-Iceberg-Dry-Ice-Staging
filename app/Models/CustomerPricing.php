<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPricing extends Model
{
    public const DELIVERY_NOVEX = 'novex';
    public const DELIVERY_FIXED = 'fixed';
    public const DELIVERY_NONE  = 'none';

    protected $table = 'customer_pricing';

    protected $fillable = [
        'customer_id',
        'ice_price',
        'box_price',
        'hazmat_fee',
        'delivery_fee',
        'delivery_mode',
        'pickup_fee',
        'other_charges',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
