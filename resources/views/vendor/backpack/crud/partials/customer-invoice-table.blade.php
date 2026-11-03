{{-- One group of a customer's invoices (outstanding, paid or drafts). Expects $invoices and $empty; $showPaidOn is optional. --}}
@php
    $showPaidOn = $showPaidOn ?? false;
    $fmtDate = function ($value) {
        if (!$value) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            // Older payments stored the date with colons (2026:10:05 ...); show the day part.
            return str_replace(':', '-', substr((string) $value, 0, 10));
        }
    };
@endphp

@if(count($invoices))
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead>
            <tr>
                <th>Invoice</th>
                <th>Invoice date</th>
                <th>Period</th>
                <th class="text-end">Total</th>
                @if($showPaidOn)
                    <th>Paid on</th>
                @endif
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->status == \App\Models\Invoice::STATUS_FINALIZED ? '#' . $invoice->invoice_number : 'Draft' }}</td>
                    <td>{{ $fmtDate($invoice->invoice_date) }}</td>
                    <td class="text-muted small">
                        @if($invoice->period_start && $invoice->period_end)
                            {{ \Carbon\Carbon::parse($invoice->period_start)->format('M j') }} &ndash; {{ \Carbon\Carbon::parse($invoice->period_end)->format('M j, Y') }}
                        @endif
                    </td>
                    <td class="text-end">${{ number_format($invoice->total_amount, 2) }}</td>
                    @if($showPaidOn)
                        <td>{{ $fmtDate($invoice->paid_at) }}</td>
                    @endif
                    <td>
                        <button class="btn btn-primary btn-view la la-eye view-invoice-btn"
                                data-url="{{ route('consolidated.invoice.view', $invoice->id) }}"
                                data-invoice-id="{{$invoice->id}}"
                                title="View Invoice">
                            <i class=""></i>
                        </button>
                        @if($invoice->payment_status !== \App\Models\Invoice::PAID)
                            <button class="btn btn-danger btn-view la la-trash delete-invoice-btn"
                                    onclick="deleteInvoice('{{ route('consolidated.invoice.delete', $invoice->id) }}', '{{ $invoice->status == \App\Models\Invoice::STATUS_FINALIZED ? '#' . $invoice->invoice_number : 'this draft' }}')"
                                    title="Delete Invoice">
                                <i class=""></i>
                            </button>
                        @endif
                        @if($invoice->status == \App\Models\Invoice::STATUS_FINALIZED)
                            <button class="btn btn-primary btn-view la la-envelope send-invoice-btn"
                                    onclick="sendInvoiceEmail('{{ route('consolidated.invoice.email', $invoice->id) }}')"
                                    data-invoice-id="{{$invoice->id}}"
                                    title="Mail Invoice">
                                <i class=""></i>
                            </button>
                            <button class="btn btn-secondary btn-view la la-history view-email-history-btn"
                                    onclick="viewEmailHistory('{{ route('consolidated.invoice.email-history', $invoice->id) }}')"
                                    title="View Send History">
                                <i class=""></i>
                            </button>
                            @if(in_array($invoice->payment_status, [\App\Models\Invoice::PENDING, \App\Models\Invoice::FAILED]))
                                <button class="btn btn-success btn-view la la-check-circle mark-paid-btn"
                                        onclick="markInvoicePaid('{{ route('consolidated.invoice.paid', $invoice->id) }}', '#{{ $invoice->invoice_number }}')"
                                        title="Payment Done">
                                    <i class=""></i>
                                </button>
                                <a class="btn btn-secondary btn-view la la-edit"
                                   href="{{ route('admin.invoice-generator.index', ['edit' => $invoice->id]) }}"
                                   title="Edit Invoice">
                                    <i class=""></i>
                                </a>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-muted">{{ $empty }}</p>
@endif
