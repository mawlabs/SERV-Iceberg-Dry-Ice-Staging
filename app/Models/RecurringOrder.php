<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringOrder extends Model
{
    const OPEN = 'open';
    const COMPLETED = 'completed';
    const CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id',
        'scheduled_delivery_date',
        'status',
        'recurring_payment_status',
        'invoice_id'
    ];

    protected $casts = [
        'scheduled_delivery_date' => 'date',
    ];


    /** On any invoice, draft or finalized. */
    public function isInAnyInvoice(): bool
    {
        return !is_null($this->invoice_id)
            || InvoiceOrders::where('invoiceable_type', self::class)->where('invoiceable_id', $this->id)->exists();
    }

    public function invoice()
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }
}
