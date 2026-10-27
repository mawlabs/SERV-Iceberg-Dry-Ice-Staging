<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\CustomerResetPasswordNotification;


class Customer extends Authenticatable
{
    use Notifiable;
    use CrudTrait;
    use HasFactory;

  protected $fillable = [
    'name',
    'email',
    'password',
    'phone',
    'address',
    'city',
    'postal_code',
    'province',
    'supply_location_id',
    'attention'
  ];

    protected $hidden = ['password'];


    // Customer.php
    public function orders()
    {
        return $this->hasMany(Order::class);
    }




    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomerResetPasswordNotification($token));
    }




    /**
     * Scope to search customers by email, name, or phone
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('email', 'LIKE', "%{$term}%")
                ->orWhere('name', 'LIKE', "%{$term}%")
                ->orWhere('phone', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Get full address
     */
    public function getFullAddressAttribute()
    {
        return trim("{$this->address}, {$this->city}, {$this->province} {$this->postal_code}");
    }


    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class)->active()->orderBy('is_default', 'desc');
    }

    /**
     * Get the default address
     */
    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', 1)->where('is_active', 1);
    }

    /**
     * Get all active addresses
     */
    public function activeAddresses()
    {
        return $this->hasMany(CustomerAddress::class)->where('is_active', 1);
    }




    public function getPaymentStatusBadge()
    {
        $orderInvoiceIds = \App\Models\Order::where('customer_id', $this->id)
            ->whereIn('status', ['valid', 'completed'])
            ->pluck('invoice_id')
            ->filter();

        $recurringInvoiceIds = \App\Models\RecurringOrder::join('orders', 'orders.id', '=', 'recurring_orders.order_id')
            ->where('orders.customer_id', $this->id)
            ->whereIn('recurring_orders.status', ['open', 'completed'])
            ->pluck('recurring_orders.invoice_id')
            ->filter();

        $invoiceIds = $orderInvoiceIds->merge($recurringInvoiceIds)->unique();

        if ($invoiceIds->isEmpty()) {
            return '<span class="badge bg-secondary">No Invoices</span>';
        }

        $pendingCount = \App\Models\Invoice::whereIn('id', $invoiceIds)
            ->where('payment_status', 'pending')
            ->count();

        if ($pendingCount > 0) {
            return '<span class="badge bg-danger">Pending ('.$pendingCount.')</span>';
        }

        return '<span class="badge bg-success">Paid</span>';
    }


    public function pricing()
    {
        return $this->hasOne(CustomerPricing::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class,'customer_id');
    }

    public function supplyLocation()
    {
        return $this->belongsTo(SupplyLocation::class, 'supply_location_id');
    }

}
