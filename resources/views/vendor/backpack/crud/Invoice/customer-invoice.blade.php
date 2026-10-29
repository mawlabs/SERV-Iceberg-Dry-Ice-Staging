@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid header">
        <h2 class="title text-capitalize">
            Invoice Generator
        </h2>
        <small>
            <a href="{{ url('admin/dashboard') }}" class="btn btn-manual btn-sm mx-3"><i class="la la-arrow-left mx-2"></i> Back to Dashboard</a>
        </small>
    </section>
@endsection

@section('content')
    <div class="container-fluid">

        {{-- STEP 1: Customer + Date Range Selector --}}
        <div class="table" id="selector-card">
            <div class="table-header">
                Select Customer &amp; Date Range
            </div>
            <div class="p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select id="gen-customer-id" class="form-select">
                            <option value="">Select customer...</option>
                            @foreach($customers as $customer)
                                <option {{ request('customer') == $customer->id ? 'selected' : '' }} value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date <span class="text-danger">*</span></label>
                        <input type="date" id="gen-start-date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date <span class="text-danger">*</span></label>
                        <input type="date" id="gen-end-date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <button id="build-draft-btn" class="btn btn-add w-100">
                            <i class="la la-search mx-1"></i> Build Draft
                        </button>
                    </div>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="gen-include-invoiced">
                    <label class="form-check-label" for="gen-include-invoiced">
                        Include orders already invoiced or marked paid
                        <small class="text-muted">&mdash; they may then appear on more than one invoice, so check for duplicates</small>
                    </label>
                </div>
            </div>
        </div>

        {{-- Saved (unfinalized) drafts the admin can resume or discard --}}
        @if(isset($drafts) && $drafts->count())
            <div class="table" id="drafts-card">
                <div class="table-header">Saved Drafts <span class="text-muted" style="font-size:13px;">(not yet finalized)</span></div>
                <div class="p-3">
                    <div class="table-responsive-wrapper">
                        <table id="drafts-table" class="w-100">
                            <thead>
                            <tr>
                                <th>Draft #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($drafts as $d)
                                <tr data-draft-row="{{ $d->id }}">
                                    <td>#{{ $d->id }}</td>
                                    <td>{{ optional($d->customer)->name ?? 'N/A' }}</td>
                                    <td>${{ number_format($d->total_amount, 2) }}</td>
                                    <td>{{ optional($d->created_at)->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <button class="btn btn-add btn-sm resume-draft-btn" data-id="{{ $d->id }}">Resume</button>
                                        <button class="btn btn-secondary btn-sm discard-draft-btn" data-id="{{ $d->id }}">Discard</button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- STEP 2: Draft Review / Edit --}}
        <div class="table" id="draft-card" style="display:none;">
            <div class="table-header d-flex justify-content-between align-items-center">
                <span id="draft-card-title">Draft Invoice</span>
                <span class="text-muted" style="font-size: 14px;" id="draft-range-label"></span>
            </div>

            <div class="p-3">

                <div class="row">
                    <div class="col-md-7">
                        <h5 class="mb-2"><i class="la la-list"></i> Orders Included</h5>
                        <div class="table-responsive-wrapper mb-4">
                            <table id="order-refs-table">
                                <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Date</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody id="order-refs-body">
                                </tbody>
                            </table>
                        </div>

                        <h5 class="mb-2"><i class="la la-cubes"></i> Line Items</h5>
                        <div class="table-responsive-wrapper mb-4">
                            <table id="line-items-table">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                    <th>GST</th>
                                    <th>PST</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody id="line-items-body">
                                </tbody>
                            </table>
                        </div>

                        <h5 class="mb-2"><i class="la la-plus-circle"></i> Add Line Item</h5>
                        <div class="row g-2 align-items-end mb-1">
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="new-line-date">Date</label>
                                <input type="date" id="new-line-date" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-0" for="new-line-item">Item</label>
                                <select id="new-line-item" class="form-select form-select-sm">
                                    <optgroup label="Products">
                                        @foreach($products as $product)
                                            <option value="product:{{ $product->id }}">{{ $product->product_name }}</option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="Fees">
                                        <option value="fee:delivery">Delivery</option>
                                        <option value="fee:pickup">Pickup Fee</option>
                                        <option value="fee:hazmat">Hazmat Fee</option>
                                    </optgroup>
                                    <option value="custom">Custom item...</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="new-line-qty">Qty</label>
                                <input type="number" id="new-line-qty" class="form-control form-control-sm" value="1" min="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" for="new-line-price">Unit price</label>
                                <input type="number" id="new-line-price" class="form-control form-control-sm" placeholder="Auto" step="0.001" min="0">
                            </div>
                            <div class="col-md-3">
                                <button id="add-line-btn" class="btn btn-add btn-sm w-100">Add Line</button>
                            </div>
                        </div>
                        <div class="row g-2 mb-3" id="new-line-description-row" style="display:none;">
                            <div class="col-md-12">
                                <input type="text" id="new-line-description" class="form-control form-control-sm" placeholder="Description of the custom item" maxlength="255">
                            </div>
                        </div>
                        <small class="text-muted d-block mb-3">Price is filled in from the customer's pricing when left blank. Lines added here are not tied to an order.</small>

                        <h5 class="mb-2"><i class="la la-plus-circle"></i> Add Flat Charge / Discount</h5>
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-3">
                                <select id="new-charge-key" class="form-select form-select-sm">
                                    <option value="custom">Custom Charge</option>
                                    <option value="discount">Discount</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="new-charge-label" class="form-control form-control-sm" placeholder="Label">
                            </div>
                            <div class="col-md-3">
                                <input type="number" id="new-charge-amount" class="form-control form-control-sm" placeholder="Amount (use - for discount)" step="0.01">
                            </div>
                            <div class="col-md-2">
                                <button id="add-charge-btn" class="btn btn-add btn-sm w-100">Add</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <h5 class="mb-2"><i class="la la-dollar-sign"></i> Cost Summary</h5>
                        <div class="p-3 rounded" style="background: rgba(245, 246, 250, 1);">
                            <div id="flat-charges-summary"></div>
                            <hr>
                            <div class="m-1">
                                <p class="m-0 d-flex justify-content-between align-items-center">
                                    Sub-Total: <strong id="summary-subtotal">$0.00</strong>
                                </p>
                            </div>
                            <div class="m-1">
                                <p class="m-0 d-flex justify-content-between align-items-center">
                                    GST (5%): <strong id="summary-gsttotal">$0.00</strong>
                                </p>
                            </div>
                            <div class="m-1">
                                <p class="m-0 d-flex justify-content-between align-items-center">
                                    PST (7%, boxes only): <strong id="summary-psttotal">$0.00</strong>
                                </p>
                            </div>
                            <div class="m-1">
                                <p class="m-0 d-flex justify-content-between align-items-center">
                                    Flat Charges: <strong id="summary-flattotal">$0.00</strong>
                                </p>
                            </div>
                            <hr>
                            <div class="m-1">
                                <p class="d-flex justify-content-between align-items-center m-0">
                                    <strong>TOTAL:</strong> <strong id="summary-total">$0.00</strong>
                                </p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="invoice-date" class="form-label" style="font-size:14px;font-weight:600;">Invoice Date</label>
                            <input type="date" id="invoice-date" class="form-control">
                        </div>

                        <div class="mt-3">
                            <label for="invoice-notes" class="form-label" style="font-size:14px;font-weight:600;">Invoice Notes</label>
                            <textarea id="invoice-notes" class="form-control" rows="3"
                                placeholder="Optional notes"></textarea>
                        </div>

                        <div class="mt-3">
                            <button id="preview-draft-btn" class="btn btn-outline-secondary w-100">
                                <i class="la la-eye"></i> Preview Invoice
                            </button>
                        </div>

                        <div class="mt-2 d-flex gap-2">
                            <button id="finalize-btn" class="btn btn-primary flex-grow-1">
                                <i class="la la-check-circle"></i> Confirm &amp; Generate Invoice
                            </button>
                            <button id="cancel-draft-btn" class="btn btn-secondary">
                                Save &amp; Close
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- STEP 3: Result --}}
        <div class="table" id="result-card" style="display:none;">
            <div class="table-header">Invoice Generated</div>
            <div class="p-4 text-center">
                <i class="la la-check-circle text-success" style="font-size: 60px;"></i>
                <h4 class="mt-3" id="result-title">Invoice <span id="result-invoice-number"></span> created successfully</h4>
                <a id="result-pdf-link" href="#" target="_blank" class="btn btn-primary mt-3">
                    <i class="la la-file-pdf"></i> Download PDF
                </a>
                <button id="start-new-btn" class="btn btn-secondary mt-3 mx-2">Start New Invoice</button>
            </div>
        </div>

    </div>
@endsection

@section('after_styles')
    @vite(['resources/scss/app.scss', 'resources/css/custom.css'])

    <style>
        .container-fluid h2.title {
            font-weight: 900;
            font-size: 36px;
            letter-spacing: -0.11px;
            margin: 0px;
        }

        .container-fluid.header {
            display: flex;
            justify-content: space-between;
            padding: 0 24px 20px;
            align-items: center;
        }

        .btn-manual {
            background: rgba(232, 232, 232, 1);
            border: 1px solid black;
            letter-spacing: -0.11px;
            color: black;
            border-radius: 10px;
            box-shadow: 0px 4px 4px 0px rgba(0, 0, 0, 0.25);
        }

        .btn-add {
            background-color: var(--tblr-primary);
            border: 1px solid var(--tblr-primary);
            color: var(--tblr-light);
            border-radius: 10px;
            box-shadow: 0px 4px 4px 0px rgba(0, 0, 0, 0.25);
        }

        .btn-add:hover {
            background-color: #0246a5;
            color: var(--tblr-light);
        }

        .table {
            margin-bottom: 30px;
            border-radius: 8px;
            background: white;
            box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            color: #221e26;
            padding: 16px 20px;
            font-weight: 700;
            font-size: 22px;
            line-height: 1.4;
            letter-spacing: -0.11px;
            border-bottom: 1px solid #f1f3f4;
        }

        .table-responsive-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #order-refs-table, #line-items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        #order-refs-table thead, #line-items-table thead {
            background-color: #221e26;
        }

        #order-refs-table thead th, #line-items-table thead th {
            color: white;
            font-weight: 500;
            font-size: 13px;
            padding: 10px 12px;
            border: none;
            text-align: left;
        }

        #order-refs-table tbody tr, #line-items-table tbody tr {
            background-color: white;
            border-bottom: 1px solid #f1f3f4;
        }

        #order-refs-table tbody td, #line-items-table tbody td {
            padding: 8px 12px;
            font-size: 13px;
            vertical-align: middle;
        }

        .remove-ref-btn, .remove-charge-btn, .remove-line-btn {
            background: none;
            border: none;
            color: #d33;
            cursor: pointer;
            font-size: 16px;
        }

        .badge-order-type {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-onetime { background: #e7f1ff; color: #0256c5; }
        .badge-recurring { background: #fff3cd; color: #997404; }

        .line-item-input {
            width: 90px;
            padding: 2px 6px;
            font-size: 13px;
        }

        #order-refs-table.editing-finalized .remove-ref-btn { display: none; }

        footer { display: none; }
    </style>
@endsection

@push('after_scripts')
    @vite(['resources/js/app.js'])

    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            let currentDraft = null;

            const selectorCard = document.getElementById('selector-card');
            const draftCard     = document.getElementById('draft-card');
            const resultCard    = document.getElementById('result-card');

            // Make the customer dropdown searchable (same as the manual order form)
            if (window.jQuery && jQuery.fn.select2) {
                jQuery('#gen-customer-id').select2({
                    placeholder: 'Search customer...',
                    allowClear: true,
                    width: '100%'
                });
            }

            // Default the range to the current month (first to last day) unless a value is already set.
            (function () {
                const now = new Date();
                const iso = (d) => d.toLocaleDateString('en-CA');
                const start = document.getElementById('gen-start-date');
                const end = document.getElementById('gen-end-date');
                if (start && !start.value) start.value = iso(new Date(now.getFullYear(), now.getMonth(), 1));
                if (end && !end.value) end.value = iso(new Date(now.getFullYear(), now.getMonth() + 1, 0));
            })();

            function fmt(n) {
                return '$' + Number(n).toFixed(2);
            }

            let currentDraftId = null;

            function renderDraft(draft) {
                currentDraft = draft;
                currentDraftId = draft.id;

                // Show the range the admin selected, not the dates of the orders that happened to be found.
                const rangeStart = draft.period_start || draft.start_date || '';
                const rangeEnd   = draft.period_end || draft.end_date || '';
                const range = rangeStart + (rangeStart ? ' to ' : '') + rangeEnd;
                const editing = draft.status === 'finalized';
                document.getElementById('draft-range-label').textContent = editing
                    ? 'Editing invoice #' + draft.invoice_number + ' (number and date stay as issued)'
                    : 'Draft #' + draft.id + (range ? ' · ' + range : '');
                document.getElementById('draft-card-title').textContent = editing ? 'Edit Invoice' : 'Draft Invoice';
                document.getElementById('finalize-btn').innerHTML = editing
                    ? '<i class="la la-save"></i> Save Changes'
                    : '<i class="la la-check-circle"></i> Confirm &amp; Generate Invoice';
                document.getElementById('invoice-date').disabled = editing;
                document.getElementById('order-refs-table').classList.toggle('editing-finalized', editing);

                // Order refs
                const refsBody = document.getElementById('order-refs-body');
                refsBody.innerHTML = '';
                draft.order_refs.forEach((ref) => {
                    const badgeClass = ref.order_type === 'recurring' ? 'badge-recurring' : 'badge-onetime';
                    refsBody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td><span class="badge-order-type ${badgeClass}">${ref.order_type}</span></td>
                    <td>${ref.label}${(ref.also_on && ref.also_on.length) ? ' <span class="badge bg-danger" title="This order is already on another invoice. Remove it here if that would bill it twice.">Already on invoice ' + ref.also_on.join(', ') + '</span>' : ''}${ref.marked_paid ? ' <span class="badge bg-secondary" title="This order is marked paid in the system.">Marked paid</span>' : ''}${ref.possible_duplicate ? ' <span class="badge bg-warning text-dark" title="A one-time order and a recurring delivery fall on the same day. Remove one if it is a duplicate or was cancelled.">Same day as another order - check</span>' : ''}</td>
                    <td>${ref.delivery_date ?? ''}</td>
                    <td>
                        <button class="remove-ref-btn" data-type="${ref.invoiceable_type}" data-id="${ref.invoiceable_id}" title="Remove from invoice">
                            <i class="la la-times-circle"></i>
                        </button>
                    </td>
                </tr>
            `);
                });

                // Line items
                const liBody = document.getElementById('line-items-body');
                liBody.innerHTML = '';
                draft.line_items.forEach((li) => {
                    // A Delivery row at $0 means Novex couldn't quote it: price needs entering.
                    const needsPrice = li.description === 'Delivery' && Number(li.unit_price) === 0;
                    liBody.insertAdjacentHTML('beforeend', `
                <tr class="${needsPrice ? 'table-warning' : ''}">
                    <td>${li.delivery_date || ''}</td>
                    <td>${li.description}${needsPrice ? ' <small class="text-danger">(Novex quote unavailable - enter price)</small>' : ''}</td>
                    <td><input type="number" class="form-control form-control-sm line-item-input li-qty" data-id="${li.id}" value="${li.quantity}" min="0"></td>
                    <td><input type="number" class="form-control form-control-sm line-item-input li-price" data-id="${li.id}" value="${li.unit_price}" step="0.001" min="0"></td>
                    <td>${fmt(li.total_price)}</td>
                    <td>${fmt(li.gst)}</td>
                    <td>${fmt(li.pst)}</td>
                    <td>${li.manual ? `<button class="remove-line-btn" data-id="${li.id}" title="Remove this line"><i class="la la-times-circle"></i></button>` : ''}</td>
                </tr>
            `);
                });

                // Flat charges
                const flatSummary = document.getElementById('flat-charges-summary');
                flatSummary.innerHTML = '';
                draft.flat_charges.forEach((fc) => {
                    const label = fc.label || fc.charge_key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                    flatSummary.insertAdjacentHTML('beforeend', `
                <p class="m-0 d-flex justify-content-between align-items-center">
                    ${label}
                    <span>
                        <strong class="me-2">${fc.amount < 0 ? '-' : ''}${fmt(Math.abs(fc.amount))}</strong>
                        <button class="remove-charge-btn" data-id="${fc.id}" title="Remove charge"><i class="la la-times-circle"></i></button>
                    </span>
                </p>
            `);
                });

                document.getElementById('invoice-notes').value = draft.notes || '';
                document.getElementById('invoice-date').value = draft.invoice_date || '';
                // New hand-added lines default to the last day of the invoice period.
                const newLineDate = document.getElementById('new-line-date');
                if (!newLineDate.value) newLineDate.value = draft.period_end || draft.end_date || draft.invoice_date || '';

                document.getElementById('summary-subtotal').textContent  = fmt(draft.totals.sub_total);
                document.getElementById('summary-gsttotal').textContent  = fmt(draft.totals.gst_total);
                document.getElementById('summary-psttotal').textContent  = fmt(draft.totals.pst_total);
                document.getElementById('summary-flattotal').textContent = fmt(draft.totals.flat_total);
                document.getElementById('summary-total').textContent     = fmt(draft.totals.total);

                selectorCard.style.display = 'none';
                const draftsCard = document.getElementById('drafts-card');
                if (draftsCard) draftsCard.style.display = 'none';
                draftCard.style.display = 'block';
                resultCard.style.display = 'none';
            }

            function postJson(url, payload) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                }).then(res => res.json());
            }

            // Send an edit action against the current draft and re-render the response.
            function sendDraftAction(payload) {
                payload.invoice_id = currentDraftId;
                if (currentDraft && currentDraft.status === 'finalized') payload.editing_finalized = true;
                return postJson('{{ route('admin.invoice-generator.draft.update') }}', payload).then(data => {
                    if (data.success) renderDraft(data.draft);
                    else Swal.fire('Error', data.message || 'Update failed.', 'error');
                    return data;
                });
            }

            document.getElementById('build-draft-btn').addEventListener('click', function () {
                const customerId = document.getElementById('gen-customer-id').value;
                const startDate  = document.getElementById('gen-start-date').value;
                const endDate    = document.getElementById('gen-end-date').value;

                if (!customerId || !startDate || !endDate) {
                    Swal.fire('Missing Information', 'Please select a customer and date range.', 'warning');
                    return;
                }

                postJson('{{ route('admin.invoice-generator.draft') }}', {
                    customer_id: customerId,
                    start_date: startDate,
                    end_date: endDate,
                    include_invoiced: document.getElementById('gen-include-invoiced').checked
                }).then(data => {
                    if (data.success) {
                        renderDraft(data.draft);
                        if (data.using_default_pricing) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Default Pricing Applied',
                                text: 'No custom pricing found for this customer. Product default prices have been used. You can adjust line items manually before finalizing.',
                                confirmButtonText: 'OK'
                            });
                        }
                    } else {
                        Swal.fire('No Orders Found', data.message, 'info');
                    }
                });
            });

            document.getElementById('order-refs-body').addEventListener('click', function (e) {
                const btn = e.target.closest('.remove-ref-btn');
                if (!btn) return;
                sendDraftAction({
                    action: 'remove_order_ref',
                    invoiceable_type: btn.dataset.type,
                    invoiceable_id: btn.dataset.id
                });
            });

            document.getElementById('new-line-item').addEventListener('change', function () {
                document.getElementById('new-line-description-row').style.display = this.value === 'custom' ? 'flex' : 'none';
            });

            document.getElementById('add-line-btn').addEventListener('click', function () {
                const date = document.getElementById('new-line-date').value;
                const item = document.getElementById('new-line-item').value;
                const qty  = document.getElementById('new-line-qty').value;

                if (!date || !qty) {
                    Swal.fire('Missing Information', 'Please choose a date and a quantity.', 'warning');
                    return;
                }
                if (item === 'custom' && !document.getElementById('new-line-description').value.trim()) {
                    Swal.fire('Missing Description', 'Please describe the custom item.', 'warning');
                    return;
                }

                sendDraftAction({
                    action: 'add_line_item',
                    delivery_date: date,
                    item: item,
                    quantity: qty,
                    unit_price: document.getElementById('new-line-price').value,
                    description: document.getElementById('new-line-description').value
                }).then(data => {
                    if (data.success) {
                        document.getElementById('new-line-price').value = '';
                        document.getElementById('new-line-description').value = '';
                    }
                });
            });

            document.getElementById('line-items-body').addEventListener('click', function (e) {
                const btn = e.target.closest('.remove-line-btn');
                if (!btn) return;
                sendDraftAction({ action: 'remove_line_item', line_item_id: btn.dataset.id });
            });

            document.getElementById('add-charge-btn').addEventListener('click', function () {
                const chargeKey = document.getElementById('new-charge-key').value;
                const label     = document.getElementById('new-charge-label').value;
                const amount    = document.getElementById('new-charge-amount').value;

                if (!amount) {
                    Swal.fire('Missing Amount', 'Please enter an amount.', 'warning');
                    return;
                }

                sendDraftAction({
                    action: 'add_flat_charge',
                    charge_key: chargeKey,
                    label: label,
                    amount: amount
                }).then(data => {
                    if (data.success) {
                        document.getElementById('new-charge-label').value = '';
                        document.getElementById('new-charge-amount').value = '';
                    }
                });
            });

            document.getElementById('flat-charges-summary').addEventListener('click', function (e) {
                const btn = e.target.closest('.remove-charge-btn');
                if (!btn) return;
                sendDraftAction({ action: 'remove_flat_charge', flat_charge_id: btn.dataset.id });
            });

            document.getElementById('line-items-body').addEventListener('change', function (e) {
                const input = e.target;
                if (!input.classList.contains('li-qty') && !input.classList.contains('li-price')) return;

                const payload = { action: 'update_line_item', line_item_id: input.dataset.id };
                if (input.classList.contains('li-qty')) payload.quantity = input.value;
                if (input.classList.contains('li-price')) payload.unit_price = input.value;
                sendDraftAction(payload);
            });

            // Persist notes on blur so they survive resume.
            document.getElementById('invoice-notes').addEventListener('blur', function () {
                if (!currentDraftId) return;
                sendDraftAction({ action: 'update_notes', notes: this.value });
            });

            // Persist the invoice date on change so preview and finalize use it.
            document.getElementById('invoice-date').addEventListener('change', function () {
                if (!currentDraftId || !this.value) return;
                sendDraftAction({ action: 'update_invoice_date', invoice_date: this.value });
            });

            // Preview the invoice as it will actually look, before finalizing.
            const previewDraftModal     = new bootstrap.Modal(document.getElementById('previewDraftModal'));
            const previewDraftModalBody = document.getElementById('previewDraftModalBody');
            const previewDraftLoader    = document.getElementById('previewDraftLoader');
            const previewDraftUrlTemplate = @json(route('consolidated.invoice.view', ['invoice' => 999999999]));

            document.getElementById('preview-draft-btn').addEventListener('click', function () {
                if (!currentDraftId) return;

                previewDraftModalBody.innerHTML = '';
                previewDraftLoader.style.display = 'block';
                previewDraftModalBody.appendChild(previewDraftLoader);

                previewDraftModal.show();

                fetch(previewDraftUrlTemplate.replace('999999999', currentDraftId), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                    .then(res => {
                        if (!res.ok) throw new Error('Failed to load preview');
                        return res.text();
                    })
                    .then(html => {
                        previewDraftLoader.style.display = 'none';
                        // The fetched HTML is a full standalone document (its own
                        // <style> block with rules like "body { padding: 40px 0 }")
                        // meant for the PDF. Loading it via innerHTML would leak
                        // those rules onto this live page's real <body>. An iframe
                        // keeps the fetched document's styles fully isolated.
                        const iframe = document.createElement('iframe');
                        iframe.style.cssText = 'width:100%; height:75vh; border:0; display:block;';
                        previewDraftModalBody.innerHTML = '';
                        previewDraftModalBody.appendChild(iframe);
                        iframe.srcdoc = html;
                    })
                    .catch(() => {
                        previewDraftModalBody.innerHTML = `
                    <div class="alert alert-danger m-3">
                        Failed to load the invoice preview. Please try again.
                    </div>`;
                    });
            });

            document.getElementById('cancel-draft-btn').addEventListener('click', function () {
                // Draft is already saved in the DB — just close the editor and
                // refresh so it shows under "Saved Drafts".
                location.reload();
            });

            document.getElementById('finalize-btn').addEventListener('click', function () {
                if (currentDraft && currentDraft.status === 'finalized') {
                    Swal.fire({
                        title: 'Save changes to this invoice?',
                        html: `Invoice #${currentDraft.invoice_number} keeps its number and date. New total: <strong>${document.getElementById('summary-total').textContent}</strong>`,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, save'
                    }).then(result => {
                        if (!result.isConfirmed) return;

                        postJson('{{ route('admin.invoice-generator.edit.save') }}', {
                            invoice_id: currentDraftId,
                            notes: document.getElementById('invoice-notes').value
                        }).then(data => {
                            if (data.success) {
                                document.getElementById('result-title').innerHTML = 'Invoice <span id="result-invoice-number">#' + data.invoice_number + '</span> updated successfully';
                                document.getElementById('result-pdf-link').href = data.pdf_url;
                                draftCard.style.display = 'none';
                                resultCard.style.display = 'block';
                            } else {
                                Swal.fire('Error', data.message || 'Failed to save changes.', 'error');
                            }
                        });
                    });
                    return;
                }

                Swal.fire({
                    title: 'Generate this invoice?',
                    html: `Total amount: <strong>${document.getElementById('summary-total').textContent}</strong>`,
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, generate'
                }).then(result => {
                    if (!result.isConfirmed) return;

                    postJson('{{ route('admin.invoice-generator.finalize') }}', {
                        invoice_id: currentDraftId,
                        invoice_date: document.getElementById('invoice-date').value,
                        notes: document.getElementById('invoice-notes').value
                    }).then(data => {
                        if (data.success) {
                            document.getElementById('result-invoice-number').textContent = '#' + data.invoice_id;
                            document.getElementById('result-pdf-link').href = data.pdf_url;
                            draftCard.style.display = 'none';
                            resultCard.style.display = 'block';
                        } else {
                            Swal.fire('Error', data.message || 'Failed to generate invoice.', 'error');
                        }
                    });
                });
            });

            document.getElementById('start-new-btn').addEventListener('click', function () {
                location.reload();
            });

            // ---- Saved drafts: resume / discard ----
            document.querySelectorAll('.resume-draft-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    fetch('{{ url('admin/invoice-generator/draft') }}/' + this.dataset.id + '/resume', {
                        headers: { 'Accept': 'application/json' }
                    }).then(r => r.json()).then(data => {
                        if (data.success) renderDraft(data.draft);
                        else Swal.fire('Error', data.message || 'Could not load draft.', 'error');
                    });
                });
            });

            document.querySelectorAll('.discard-draft-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.dataset.id;
                    Swal.fire({
                        title: 'Discard this draft?',
                        text: 'The reserved orders will become available again.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, discard'
                    }).then(res => {
                        if (!res.isConfirmed) return;
                        postJson('{{ route('admin.invoice-generator.draft.discard') }}', { invoice_id: id }).then(data => {
                            if (data.success) {
                                const row = document.querySelector(`[data-draft-row="${id}"]`);
                                if (row) row.remove();
                            } else {
                                Swal.fire('Error', data.message || 'Could not discard.', 'error');
                            }
                        });
                    });
                });
            });

            // Auto-open a finalized invoice in edit mode when arriving from its Edit button (?edit=<id>).
            @if(!empty($editInvoiceId))
            fetch('{{ url('admin/invoice-generator') }}/{{ $editInvoiceId }}/edit-data', {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                if (data.success) renderDraft(data.draft);
                else Swal.fire('Cannot edit', data.message || 'Could not load invoice.', 'error');
            });
            @endif

            // Auto-open a draft when arriving from an order-list "Generate Invoice" click (?draft=<id>).
            @if(!empty($openDraftId))
            fetch('{{ url('admin/invoice-generator/draft') }}/{{ $openDraftId }}/resume', {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                if (data.success) renderDraft(data.draft);
            });
            @endif

        });
    </script>
@endpush
<div class="modal fade" id="previewDraftModal" tabindex="-1" aria-labelledby="previewDraftModal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewDraftModalLabel">Invoice Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 px-5" id="previewDraftModalBody">
                <div class="text-center py-5" id="previewDraftLoader">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading preview...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
