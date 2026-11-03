@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid header">
        <h2 class="title text-capitalize">
            Invoices
        </h2>
        <small>
            <a href="{{ route('admin.invoice-generator.index') }}" class="btn btn-manual btn-sm mx-3"><i class="la la-plus mx-2"></i> Generate New Invoice</a>
        </small>
    </section>
@endsection

@section('content')
    <div class="container-fluid">

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.invoice-generator.list') }}" class="card p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="f-customer">Customer</label>
                    <select id="f-customer" name="customer_id" class="form-select form-select-sm">
                        <option value="">All customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) $filters['customer_id'] === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="f-status">Show</label>
                    <select id="f-status" name="status" class="form-select form-select-sm">
                        <option value="all" @selected($filters['status'] === 'all')>All invoices</option>
                        <option value="outstanding" @selected($filters['status'] === 'outstanding')>Outstanding</option>
                        <option value="paid" @selected($filters['status'] === 'paid')>Paid</option>
                        <option value="drafts" @selected($filters['status'] === 'drafts')>Drafts</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="f-from">Invoice date from</label>
                    <input type="date" id="f-from" name="from" class="form-control form-control-sm" value="{{ $filters['from'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="f-to">to</label>
                    <input type="date" id="f-to" name="to" class="form-control form-control-sm" value="{{ $filters['to'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="f-q">Invoice # or customer</label>
                    <input type="text" id="f-q" name="q" class="form-control form-control-sm" value="{{ $filters['q'] }}" placeholder="Search">
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100" title="Apply filters"><i class="la la-search"></i></button>
                    <a href="{{ route('admin.invoice-generator.list') }}" class="btn btn-outline-secondary btn-sm" title="Clear filters"><i class="la la-times"></i></a>
                </div>
            </div>
        </form>

        {{-- Totals for the current filter --}}
        <div class="d-flex flex-wrap gap-4 mb-3">
            <div><span class="text-muted">Invoices:</span> <strong>{{ $summary['count'] }}</strong></div>
            <div><span class="text-muted">Total:</span> <strong>${{ number_format($summary['total'], 2) }}</strong></div>
            <div><span class="text-muted">Outstanding:</span> <strong class="text-danger">${{ number_format($summary['outstanding'], 2) }}</strong></div>
        </div>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Invoice date</th>
                        <th>Period</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th>Last emailed</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $isDraft     = $invoice->status === \App\Models\Invoice::STATUS_DRAFT;
                            $isPaid      = $invoice->payment_status === \App\Models\Invoice::PAID;
                            $unpaid      = !$isDraft && in_array($invoice->payment_status, [\App\Models\Invoice::PENDING, \App\Models\Invoice::FAILED]);
                            $label       = $isDraft ? 'this draft' : '#' . $invoice->invoice_number;
                        @endphp
                        <tr>
                            <td><strong>{{ $isDraft ? 'Draft' : $invoice->invoice_number }}</strong></td>
                            <td>
                                @if($invoice->customer)
                                    <a href="{{ route('customer.view', $invoice->customer_id) }}">{{ $invoice->customer->name }}</a>
                                @endif
                            </td>
                            <td>{{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '' }}</td>
                            <td class="text-muted small">
                                @if($invoice->period_start && $invoice->period_end)
                                    {{ \Carbon\Carbon::parse($invoice->period_start)->format('M j') }} &ndash; {{ \Carbon\Carbon::parse($invoice->period_end)->format('M j, Y') }}
                                @endif
                            </td>
                            <td class="text-end">${{ number_format($invoice->total_amount, 2) }}</td>
                            <td>
                                @if($isDraft)
                                    <span class="badge bg-secondary">Draft</span>
                                @elseif($isPaid)
                                    <span class="badge bg-success">Paid</span>
                                @elseif($invoice->payment_status === \App\Models\Invoice::FAILED)
                                    <span class="badge bg-danger">Payment failed</span>
                                @else
                                    <span class="badge bg-warning text-dark">Outstanding</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $invoice->last_emailed_at ? \Carbon\Carbon::parse($invoice->last_emailed_at)->format('Y-m-d H:i') : ($isDraft ? '' : 'Never') }}
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-primary view-invoice-btn" title="View invoice"
                                        data-url="{{ route('consolidated.invoice.view', $invoice->id) }}"
                                        data-invoice-id="{{ $invoice->id }}" data-label="{{ $label }}">
                                    <i class="la la-eye"></i>
                                </button>
                                @if($isDraft)
                                    <a class="btn btn-sm btn-secondary" title="Open draft in the generator"
                                       href="{{ route('admin.invoice-generator.index', ['draft' => $invoice->id]) }}"><i class="la la-edit"></i></a>
                                @else
                                    <a class="btn btn-sm btn-success" title="Download PDF"
                                       href="{{ route('admin.invoice-generator.pdf', $invoice->id) }}"><i class="la la-download"></i></a>
                                    <button type="button" class="btn btn-sm btn-primary" title="Mail invoice"
                                            onclick="sendInvoiceEmail('{{ route('consolidated.invoice.email', $invoice->id) }}', '{{ $label }}')">
                                        <i class="la la-envelope"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" title="View send history"
                                            onclick="viewEmailHistory('{{ route('consolidated.invoice.email-history', $invoice->id) }}')">
                                        <i class="la la-history"></i>
                                    </button>
                                @endif
                                @if($unpaid)
                                    <button type="button" class="btn btn-sm btn-success" title="Payment done"
                                            onclick="markInvoicePaid('{{ route('consolidated.invoice.paid', $invoice->id) }}', '{{ $label }}')">
                                        <i class="la la-check-circle"></i>
                                    </button>
                                    <a class="btn btn-sm btn-secondary" title="Edit invoice"
                                       href="{{ route('admin.invoice-generator.index', ['edit' => $invoice->id]) }}"><i class="la la-pencil"></i></a>
                                @endif
                                @if(!$isPaid)
                                    <button type="button" class="btn btn-sm btn-danger" title="Delete invoice"
                                            onclick="deleteInvoice('{{ route('consolidated.invoice.delete', $invoice->id) }}', '{{ $label }}')">
                                        <i class="la la-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No invoices match these filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $invoices->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    {{-- Invoice preview --}}
    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalLabel">Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-5" id="invoiceModalBody"></div>
                <div class="modal-footer">
                    <a href="#" id="downloadInvoiceBtn" class="btn btn-success" target="_blank">
                        <i class="la la-download"></i> Download PDF
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && jQuery.fn.select2) {
                jQuery('#f-customer').select2({ width: '100%', allowClear: true, placeholder: 'All customers' });
            }

            const invoiceModal     = new bootstrap.Modal(document.getElementById('invoiceModal'));
            const invoiceModalBody = document.getElementById('invoiceModalBody');
            const downloadBtn      = document.getElementById('downloadInvoiceBtn');

            document.querySelectorAll('.view-invoice-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    invoiceModalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading invoice...</p></div>';
                    downloadBtn.href = `/admin/invoice-generator/${this.dataset.invoiceId}/pdf`;
                    invoiceModal.show();

                    fetch(this.dataset.url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }
                    })
                        .then(res => {
                            if (!res.ok) throw new Error('Failed to load invoice');
                            return res.text();
                        })
                        .then(html => { invoiceModalBody.innerHTML = html; })
                        .catch(() => {
                            invoiceModalBody.innerHTML = '<div class="alert alert-danger m-3">Failed to load invoice. Please try again.</div>';
                        });
                });
            });

            document.getElementById('invoiceModal').addEventListener('hidden.bs.modal', function () {
                invoiceModalBody.innerHTML = '';
            });
        });

        // POST/DELETE an action, then reload the list so the row reflects the change.
        function invoiceAction(url, method, okTitle, failTitle) {
            return fetch(url, {
                method: method,
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(okTitle, data.message, 'success').then(() => window.location.reload());
                    } else {
                        Swal.fire(failTitle, data.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Something went wrong. Please try again.', 'error'));
        }

        function markInvoicePaid(url, label) {
            Swal.fire({
                title: 'Payment received?',
                text: `Are you sure the payment has been received from the customer for invoice ${label}? The invoice and all of its orders will be marked as paid.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, payment received',
            }).then(result => {
                if (result.isConfirmed) invoiceAction(url, 'POST', 'Marked as paid', 'Cannot mark as paid');
            });
        }

        function deleteInvoice(url, label) {
            Swal.fire({
                title: 'Delete invoice?',
                text: `Delete invoice ${label}? Its orders will be released so they can be invoiced again. This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it',
            }).then(result => {
                if (result.isConfirmed) invoiceAction(url, 'DELETE', 'Deleted', 'Cannot delete');
            });
        }

        function sendInvoiceEmail(url, label) {
            Swal.fire({
                title: 'Send invoice?',
                text: `Email invoice ${label} to the customer?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, send it!',
            }).then(result => {
                if (result.isConfirmed) invoiceAction(url, 'POST', 'Sent!', 'Error');
            });
        }

        function viewEmailHistory(url) {
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        Swal.fire('Error', 'Could not load send history.', 'error');
                        return;
                    }
                    if (!data.logs.length) {
                        Swal.fire('Send History', 'This invoice has never been emailed.', 'info');
                        return;
                    }
                    const rows = data.logs.map(log => {
                        const statusLabel = log.status === 'sent'
                            ? '<span style="color:green;">Sent</span>'
                            : '<span style="color:#c0392b;">Failed</span>';
                        return `<tr>
                            <td style="padding:4px 8px;">${log.created_at}</td>
                            <td style="padding:4px 8px;">${log.sent_to}</td>
                            <td style="padding:4px 8px;">${statusLabel}</td>
                            <td style="padding:4px 8px;">${log.sent_by || 'N/A'}</td>
                        </tr>`;
                    }).join('');
                    Swal.fire({
                        title: 'Invoice Send History',
                        width: 700,
                        html: `<table style="width:100%; text-align:left; font-size:13px;">
                            <thead><tr>
                                <th style="padding:4px 8px;">When</th>
                                <th style="padding:4px 8px;">Recipient</th>
                                <th style="padding:4px 8px;">Status</th>
                                <th style="padding:4px 8px;">Sent By</th>
                            </tr></thead>
                            <tbody>${rows}</tbody>
                        </table>`,
                    });
                })
                .catch(() => Swal.fire('Error', 'Could not load send history.', 'error'));
        }
    </script>
@endpush
