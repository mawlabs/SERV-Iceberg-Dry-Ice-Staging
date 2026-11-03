<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceLineItems extends Model
{
    public const GST_RATE = 0.05;
    public const PST_RATE = 0.07;

    protected $fillable = [
        'invoice_id',
        'order_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'delivery_date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * GST is 5% and applies to every line (products and per-order fees alike).
     * PST is 7% and applies only to Styrofoam box (unit='box') product lines.
     * Confirmed by Tyler 2026-08-27. Computed live rather than stored so an
     * edited quantity/price is always reflected — never goes stale.
     */
    public function getGstAttribute(): float
    {
        return round(((float) $this->total_price) * self::GST_RATE, 2);
    }

    public function getPstAttribute(): float
    {
        if ($this->product && $this->product->unit === 'box') {
            return round(((float) $this->total_price) * self::PST_RATE, 2);
        }

        return 0.0;
    }
}
