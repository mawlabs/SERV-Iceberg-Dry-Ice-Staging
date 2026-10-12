<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Order extends Model
{
    use CrudTrait;
    use HasFactory;
    use SoftDeletes;

    const RECURRING = 'recurring';
    const NON_RECURRING = 'non-recurring';
    const COMPLETED = 'completed';
    const VALID = 'valid';
    const OPEN = 'open';
    const SKIP = 'skip';
    const CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id',
        'customer_name',
        'email',
        'phone',
        'address_id',
        'amount_of_ice',
        'amount_of_boxes',
        'origin',
        'recurring',
        'location_name',
        'address',
        'unit',
        'city',
        'postal_code',
        'province',
        'country',
        'pickup_delivery',
        'status',
        'hazmat',
        'delivery_date',
        'notes',
        'sub_total',
        'delivery_cost',
        'tax',
        'total_cost',
        'payment_status',
        'supplier_id',
        'invoice_id',
        'po',
        'created_by',
        'submission_token',
    ];

    public function scopeToday($query)
    {
        return $query->whereDate('delivery_date', Carbon::today());
    }

    public function scopeFuture($query)
    {
        return $query->whereDate('delivery_date', '>', Carbon::today());
    }

    public function scopePast($query)
    {
        return $query->whereDate('delivery_date', '<', Carbon::today());
    }

    public function scopeAll($query)
    {
        return $query;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'email', 'email');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice()
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }

    public function recurringOrders()
    {
        return $this->hasMany(RecurringOrder::class);
    }

    /**
     * True when this order (or any of its recurring occurrences) sits in a draft
     * consolidated invoice, in which case its details must not change.
     */
    public function isInDraftInvoice(): bool
    {
        return InvoiceOrders::query()
            ->whereIn('invoice_id', Invoice::draft()->select('id'))
            ->where(function ($q) {
                $q->where(fn ($q) => $q->where('invoiceable_type', self::class)->where('invoiceable_id', $this->id))
                  ->orWhere(fn ($q) => $q->where('invoiceable_type', RecurringOrder::class)
                      ->whereIn('invoiceable_id', $this->recurringOrders()->select('id')));
            })
            ->exists();
    }

    public function nextRecurringOrder()
    {
        return $this->recurringOrders()
            ->where('status', 'open')
            ->where('scheduled_delivery_date', '>', now())
            ->orderBy('scheduled_delivery_date')
            ->first();
    }

    // ✅ RENAMED: Changed from address() to customerAddress()
    public function customerAddress()
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
    }

    /**
     * Get formatted delivery address
     */
    public function getDeliveryAddressAttribute()
    {
        // Check if order uses address relationship
        if ($this->address_id && $this->relationLoaded('customerAddress') && $this->customerAddress) {
            return $this->customerAddress->full_address;
        }

        // Fallback to legacy inline address fields
        $parts = [
            $this->address, // Now this correctly refers to the column
            $this->unit ? "Unit {$this->unit}" : null,
            $this->city,
            $this->province,
            $this->postal_code
        ];

        return implode(', ', array_filter($parts));
    }
}
