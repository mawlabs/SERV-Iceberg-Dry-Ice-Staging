<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{

    const PENDING = 'pending';
    const PAID = 'paid';

    // First invoice number issued, continuing the numbering of the previous invoicing system.
    const FIRST_INVOICE_NUMBER = 11000;
    const FAILED = 'failed';

    // Lifecycle status (separate from payment_status).
    const STATUS_DRAFT = 'draft';
    const STATUS_FINALIZED = 'finalized';


    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'status',
        'invoiceable_id',
        'invoiceable_type',
        'total_amount',
        'payment_status',
        'parent_invoice_id',
        'recurring_sequence',
        'invoice_date',
        'transaction_json',
        'customer_id',
        'paid_at',
        'notes',
        'period_start',
        'period_end',
    ];

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    protected $casts = [
        'invoice_date' => 'date'
    ];

    // Polymorphic relationship - can belong to Order or RecurringOrder
    public function invoiceable()
    {
        return $this->morphTo();
    }

    // Parent invoice (for recurring invoices)
    public function parentInvoice()
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    // Child invoices (all recurring instances)
    public function childInvoices()
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id')->orderBy('recurring_sequence');
    }

    // Get next invoice in sequence
    public function nextInvoice()
    {
        return $this->hasOne(Invoice::class, 'parent_invoice_id')
            ->where('recurring_sequence', $this->recurring_sequence + 1);
    }

    // Generate the next invoice number: 3000 when the table is empty,
    // otherwise the highest existing invoice number + 1.
    //
    // Call this inside the same transaction that saves the invoice. The locking
    // read makes a second request wait for the first to commit and then see its
    // number; without it both read the same maximum, pick the same number, and
    // the second fails on the unique index (orders and finalizes failing at random
    // when two arrive together).
    public static function generateInvoiceNumber(): string
    {
        // Highest existing numeric invoice number (0 when there are no records).
        $last = (int) static::lockForUpdate()
            ->orderByRaw('CAST(invoice_number AS UNSIGNED) DESC')
            ->value('invoice_number');

        return (string) ($last < self::FIRST_INVOICE_NUMBER ? self::FIRST_INVOICE_NUMBER : $last + 1);
    }

    /**
     * File name for the invoice PDF: the customer's name and the invoice number, e.g.
     * "Black & Blue" with invoice 11005 gives BlackandBlue_11005.pdf. Spaces and symbols are
     * dropped so the name is safe on any system.
     */
    public function pdfFileName(): string
    {
        $name = \Illuminate\Support\Str::ascii((string) ($this->customer->name ?? ''));
        $name = preg_replace('/[^A-Za-z0-9]+/', '', str_replace('&', 'and', $name));

        return ($name !== '' ? $name : 'Invoice') . '_' . ($this->invoice_number ?? 'draft') . '.pdf';
    }

    /**
     * GST for the whole invoice: 5% of the subtotal, rounded once. Summing each line's already
     * rounded GST can drift by a cent or two, so the totals never use the per-line figures.
     */
    public function gstTotal(): float
    {
        $subTotal = (float) $this->lineItems()->sum('total_price');

        return round($subTotal * InvoiceLineItems::GST_RATE, 2);
    }

    /** PST for the whole invoice: 7% of the box lines' subtotal, rounded once. */
    public function pstTotal(): float
    {
        $boxTotal = (float) $this->lineItems()
            ->whereHas('product', fn ($q) => $q->where('unit', 'box'))
            ->sum('total_price');

        return round($boxTotal * InvoiceLineItems::PST_RATE, 2);
    }

    // Check if this is a recurring invoice
    public function isRecurring(): bool
    {
        return $this->invoice_type === 'recurring';
    }

    // Check if this is the original invoice
    public function isOriginal(): bool
    {
        return $this->recurring_sequence === 1 && is_null($this->parent_invoice_id);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItems::class, 'invoice_id');
    }
    public function flatCharges()
    {
        return $this->hasMany(InvoiceFlatCharges::class, 'invoice_id');
    }

    public function emailLogs()
    {
        return $this->hasMany(InvoiceEmailLog::class, 'invoice_id')->latest();
    }
}
