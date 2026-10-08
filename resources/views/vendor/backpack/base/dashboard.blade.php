@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid header">
  <h2 class="title text-capitalize">
    Dashboard
  </h2>
    <small>
        <a href="{{ url('admin/manual-payments/create') }}" class="btn btn-add btn-sm mx-3 btn-manual"><i class="la la-wallet mx-2"></i> Manual Payment</a>

        <button id="create-order-btn" data-bs-toggle="modal" data-bs-target="#orderSummaryModal" class="btn btn-add btn-sm"><i class="la la-plus mx-2"></i> New Order</button>
  </small>
</section>
@endsection

@section('content')
<div class="container-fluid">

    <div class="row">
            <div class="col-12">
                <div class="table">
                    <div class="table-header">
                        One Time Orders
                    </div>
                    <div class="table-responsive-wrapper">
                        <table>
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Delivery Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Origin</th>
                                <th>Notes</th>
                                <th>View</th>
                                <th>Payment</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($oneTimeOrders as $order)
                                <tr>
                                    <td>
                                        {{ str_pad($order->id, 4, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td>{{ $order->customer_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d') }}</td>
                                    <td>
                                        <span class="badge {{ $order['status'] == 'completed' ? 'bg-success' : 'bg-secondary'  }}">{{ $order['status'] }}</span>
                                    </td>
                                    <td>${{ isset($order->total_cost) ? $order->total_cost : '0' }}</td>
                                    <td>{{ $order['origin'] == 'manual' ? 'Manual' : 'CC'  }}</td>
                                    <td>
                                        {{-- Order Notes (truncated; click to expand) --}}
                                        @php $notesText = trim((string) $order->notes); @endphp
                                        @if($notesText === '')
                                            <span class="text-secondary">—</span>
                                        @elseif(\Illuminate\Support\Str::length($notesText) > 5)
                                            <span class="order-notes"
                                                  data-full="{{ $notesText }}"
                                                  data-short="{{ \Illuminate\Support\Str::limit($notesText, 5) }}"
                                                  title="Click to show full note">{{ \Illuminate\Support\Str::limit($notesText, 5) }}</span>
                                        @else
                                            <span class="order-notes-short">{{ $notesText }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-view la la-eye la-2x" title="View Order Details" data-order-id="{{ $order['id'] }}" data-origin="{{ $order['origin'] }}"><i class=""></i></button>
                                    </td>
                                    <td>
                                        <span class="badge {{$order['payment_status'] == 'paid' ? 'bg-success' : 'bg-danger'}}">
                                            {{ $order['payment_status'] == 'paid' ? 'PAID' : 'PENDING' }}
                                        </span>
                                        @isset($order->invoice)
                                            <button
                                                class="btn btn-sm view-invoice-btn"
                                                data-invoice-id="{{ $order->invoice->id }}"
                                                data-url="{{ route('invoice.view', $order->invoice->id) }}">
                                                <i class="las la-file-invoice-dollar"></i>
                                            </button>
                                        @endisset
                                        @if($order->origin === 'manual' && is_null($order->invoice_id))
                                            {{-- Generate a consolidated invoice draft for this account-holder order --}}
                                            <form action="{{ route('admin.invoice-generator.draft.from-order') }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="order_id" value="{{ $order->id }}">
                                                <input type="hidden" name="is_recurring" value="0">
                                                <button type="submit" class="btn btn-sm btn-success" title="Generate Invoice">
                                                    <i class="las la-file-invoice-dollar"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="col-12">
                <div class="table">
                    <div class="table-header">
                        Recurring Orders
                    </div>
                    <div class="table-responsive-wrapper">
                        <table>
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>Delivery Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Notes</th>
                                <th>View</th>
                                <th>Payment</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($allRecurringOrders as $order)
                                <tr>
                                    <td>
                                        {{-- Handle both Order and RecurringOrder --}}
                                    {{ str_pad($order instanceof \App\Models\RecurringOrder ? $order->id : $order->id, 4, '0', STR_PAD_LEFT) }}
                                    <td>
                                        {{-- Handle customer name from both sources --}}
                                        {{ $order instanceof \App\Models\RecurringOrder ? $order->order->customer_name : $order->customer_name }}
                                    </td>
                                    <td>
                                        {{-- Handle delivery date from both sources --}}
                                        {{ $order instanceof \App\Models\RecurringOrder
                                            ? \Carbon\Carbon::parse($order->scheduled_delivery_date)->format('Y-m-d')
                                            : \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d') }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $order['status'] == 'completed' ? 'bg-success' : 'bg-secondary'  }}">{{ $order['status'] }}</span>
                                    </td>
                                    <td>
                                        {{-- Handle total cost from both sources --}}
                                        ${{ $order instanceof \App\Models\RecurringOrder
                                            ? optional($order->order)->total_cost ?? 0
                                            : $order->total_cost ?? 0
                                        }}
                                    </td>
                                    <td>
                                        {{-- Order Notes (truncated; click to expand) --}}
                                        @php
                                            $notesText = trim((string) ($order instanceof \App\Models\RecurringOrder
                                                ? optional($order->order)->notes
                                                : $order->notes));
                                        @endphp
                                        @if($notesText === '')
                                            <span class="text-secondary">—</span>
                                        @elseif(\Illuminate\Support\Str::length($notesText) > 5)
                                            <span class="order-notes"
                                                  data-full="{{ $notesText }}"
                                                  data-short="{{ \Illuminate\Support\Str::limit($notesText, 5) }}"
                                                  title="Click to show full note">{{ \Illuminate\Support\Str::limit($notesText, 5) }}</span>
                                        @else
                                            <span class="order-notes-short">{{ $notesText }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order instanceof \App\Models\RecurringOrder)
                                            <button class="btn btn-primary rounded-5 la la-eye la-2x"
                                                    onclick="loadRecurringOrderDetails(1,'{{ $order->order_id }}','{{ $order->id }}')">
                                            </button>
                                        @else
                                            <button class="btn btn-primary btn-view la la-eye la-2x" data-order-id="{{ $order->id }}" data-origin="{{ $order->origin }}"></button>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order instanceof \App\Models\RecurringOrder)
                                            <span class="badge {{$order['recurring_payment_status'] == 1 ? 'bg-success' : 'bg-danger'}}">
                                            {{ $order['recurring_payment_status'] == 1 ? 'PAID' : 'PENDING' }}
                                        </span>
                                        @else
                                            <span class="badge {{$order['payment_status'] == 'paid' ? 'bg-success' : 'bg-danger'}}">
                                            {{ $order['payment_status'] == 'paid' ? 'PAID' : 'PENDING' }}
                                        </span>
                                        @endif
                                        @isset($order->invoice)
                                            <button
                                                class="btn btn-sm view-invoice-btn"
                                                data-invoice-id="{{ $order->invoice->id }}"
                                                data-url="{{ route('invoice.view', $order->invoice->id) }}">
                                                <i class="las la-file-invoice-dollar"></i>
                                            </button>
                                        @endisset
                                        @php
                                            $isRec = $order instanceof \App\Models\RecurringOrder;
                                            $genOrigin = $isRec ? optional($order->order)->origin : $order->origin;
                                        @endphp
                                        @if($genOrigin === 'manual' && is_null($order->invoice_id))
                                            {{-- Generate a consolidated invoice draft for this account-holder order --}}
                                            <form action="{{ route('admin.invoice-generator.draft.from-order') }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="order_id" value="{{ $order->id }}">
                                                <input type="hidden" name="is_recurring" value="{{ $isRec ? 1 : 0 }}">
                                                <button type="submit" class="btn btn-sm btn-success" title="Generate Invoice">
                                                    <i class="las la-file-invoice-dollar"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </div>

<!-- Modal -->
<div class="modal fade" id="orderSummaryModal" tabindex="-1" aria-labelledby="orderSummaryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="modal-content-container">


        </div>
    </div>
</div><!-- Bootstrap Modal -->

{{-- Invoice Modal --}}
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0" id="invoiceModalBody">
                {{-- Invoice HTML loads here --}}
                <div class="text-center py-5" id="invoiceLoader">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading invoice...</p>
                </div>
            </div>

            <div class="modal-footer">
                <a href="#" id="downloadInvoiceBtn" class="btn btn-success" target="_blank">
                    <i class="fas fa-download"></i> Download PDF
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
@endsection

@section('after_styles')
@vite(['resources/scss/app.scss', 'resources/css/custom.css'])

<style>


    .container-xl{
        width: 100% !important;
        max-width: 100% !important;
    }
    aside.navbar-vertical.navbar-expand-lg {
        margin-top: 110px;
    }
    nav.navbar.navbar-expand-lg {
        background: rgba(2, 86, 197, 1);
    }
    .navbar-nav svg{
        width: 15px;
    }
    .navbar-expand-lg.navbar-vertical~.navbar, .navbar-expand-lg.navbar-vertical~.page-wrapper {
        max-width: 100vw;
        max-height: 100vh;
        overflow-y: scroll;
    }
    h4.text-white {
        font-size: 15px;
    }
    a {
        text-decoration: none;
    }

    .navbar .nav-link {
      display: flex;
    }

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

    .container-fluid .btn-add {
      padding: 12px 20px;
      font-weight: 700;
      font-size: 18px;
      font-family: Nunito Sans;

    }

    .row {
        display: flex;
        flex-wrap: wrap;
    }

    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
        padding: 0 15px;
    }

    .table {
        margin-bottom: 30px;
        border-radius: 8px;

    }

    .table-header {
        color: #221e26;
        padding: 10px 0px;
        font-weight: 700;
        font-size: 24px;
        line-height: 36px;
        letter-spacing: -0.11px;

    }

    .table-header i {
        color: #0256c5;
        font-size: 26px;
        margin-right: 8px;
    }

    /* Table wrapper for horizontal scrolling */
    .table-responsive-wrapper {
        overflow: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 0 0 8px 8px;
        background: transparent;
        box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1);
        padding: 0px 5px;
        max-height: 600px;
    }

    .table table {
        width: 100%;
        min-width: 520px;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }

    .table thead {
        background-color: #221e26;
    }

    .table thead th {
        color: white;
        font-weight: 500;
        font-size: 14px;
        padding: 15px 12px;
        border: none;
        text-transform: capitalize;
        white-space: nowrap;
        vertical-align: middle;
    }

    .table tbody tr {
        background-color: white;
        border-bottom: 1px solid #f1f3f4;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .table tbody td {
        padding: 5px !important;
        font-size: 14px;
        vertical-align: middle;
        border: none;
        white-space: nowrap;
    }

    /* Badge/Status styling */
    .status, .badge {
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
        margin: 0;
    }



    /* Button styling */
    .btn-view, .btn-primary {
        font-weight: 600;
        font-size: 14px;
        line-height: 20.8px;
        letter-spacing: 0px;
        text-align: center;
        border-radius: 20px;
        padding: 6px 18px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background-color: #0256c5;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0246a5;
    }

    .btn-add {
        background-color: var(--tblr-primary);
        border: 1px solid var(--tblr-primary);
        color: var(--tblr-light);
        border-radius: 10px;
        box-shadow: 0px 4px 4px 0px rgba(0, 0, 0, 0.25);
    }

    .btn-manual {
        background: rgba(232, 232, 232, 1);
        border: 1px solid black;
        letter-spacing: -0.11px;
        color: black;
        border-radius: 10px;
        box-shadow: 0px 4px 4px 0px rgba(0, 0, 0, 0.25);
    }

    .btn-add:hover {
        background-color: #0246a5;
        color: var(--tblr-light);
    }

    .form-group.entries {
      display: flex;
      flex-direction: row-reverse;
      float: inline-start;
      gap: 5px;
      font-size: 14px;
    }

    .form-group .form-control {
      width: 62px;
      height: 23.14px;
      font-size: 14px;
      padding: 0;
    }

    /* Status Colors */


    .btn-view , .modal .btn {
        font-weight: 600;
        font-size: 14px;
        line-height: 20.8px;
        letter-spacing: 0px;
        text-align: center;
        border-radius: 20px;
        padding: 6px 18px;
    }
    .modal {
        z-index: 1050 !important;
        background: rgba(0, 0, 0, 0.6);
    }
    .modal-backdrop {
        z-index: 1040 !important;
        width: unset !important;
    }
    .modal-dialog {
        max-width: 65%;
        margin-top: 5%;
    }
    .modal .modal-title {
        font-size: 24px;
        font-weight: 800 !important;
    }
    .modal h5 {
        font-size: 18px;
        font-weight: 700;
    }

    .modal label {
        margin: 0px;
    }
    .modal input , .modal select , .modal textarea {
        border-radius: 9px !important;
        padding: 4px;
    }

    .modal input{
        color : rgba(91, 98, 107, 0.5);
    }
    .select2-container {
        width: 250px;
        border-radius: 8px;
        margin: 0px 10px;
        border: 1px solid rgba(213, 213, 213, 1);
        background: white;
    }
    span.select2-selection.select2-selection--single, .select2-selection.select2-selection--multiple ul.select2-selection__rendered {
        border: none;
        background: transparent;
    }




    @media (min-width: 1400px) {
      .container-xxl,
      .container-xl,
      .container-lg,
      .container-md,
      .container-sm,
      .container {
        max-width: 1328px;
      }
    }



    footer {
        display: none;
    }


    .order-details-card .form-group label {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }
    .order-details-card .form-group .form-control{
        width: 100% !important;
        height: 40px !important;
        padding-left: 10px;
    }
    .header-operation{
        padding-top: 15px;
    }
    .order-details-card{
        box-shadow: none !important;
    }
    @media (max-width: 992px) {
        .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
            margin-bottom: 20px;
        }

        .table table {
            min-width: 700px;
        }

        .table thead th,
        .table tbody td {
            padding: 12px 8px;
            font-size: 13px;
        }
    }

    /* Mobile Styles */
    @media (max-width: 768px) {
        .row {
            margin: 0 -10px;
        }

        .col-md-6 {
            padding: 0 10px;
        }

        .table-header {
            padding: 12px 15px;
            font-size: 20px;
            text-align: center;
        }

        .table table {
            min-width: 600px;
            font-size: 13px;
        }

        .table thead th,
        .table tbody td {
            padding: 12px 8px;
            font-size: 13px;
        }

        .status, .badge {
            padding: 4px 8px;
            font-size: 11px;
        }

        .btn-view, .btn-primary {
            padding: 6px 12px;
            font-size: 11px;
        }

        /* Mobile scrollbar styling */
        .table-responsive-wrapper::-webkit-scrollbar {
            height: 4px;
        }

        .table-responsive-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }

        .table-responsive-wrapper::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
        }
    }

    /* Extra Small Mobile */
    @media (max-width: 480px) {
        .table-header {
            padding: 10px 12px;
            font-size: 18px;
        }

        .table table {
            min-width: 500px;
        }

        .table thead th,
        .table tbody td {
            padding: 10px 6px;
            font-size: 12px;
        }

        .status, .badge {
            padding: 3px 6px;
            font-size: 10px;
        }

        .btn-view, .btn-primary {
            padding: 5px 10px;
            font-size: 10px;
        }
    }

    /* Ensure tables maintain your existing spacing */
    .table.recurring {
        margin-top: 38px;
    }

    .table.mb-3 {
        margin-bottom: 1rem;
    }


    /* Extra Small Mobile */

    /* Ensure tables maintain your existing spacing */


    .table.mb-3 {
        margin-bottom: 1rem;
    }

    .view-invoice-btn{
        width: 30px;
        height: 30px;
        border: 0px;
    }
    .view-invoice-btn i{
        font-size: 27px;
    }

    /* Order notes: single line + ellipsis, click to expand */
    .order-notes, .order-notes-short {
        display: inline-block;
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }
    .order-notes {
        cursor: pointer;
        text-decoration: underline dotted;
    }
    .order-notes.expanded {
        white-space: normal;
        overflow: visible;
        max-width: 320px;
        text-decoration: none;
    }
</style>

@endsection

@push('after_scripts')
@vite(['resources/js/app.js'])

<script>
    // Order notes: toggle between truncated and full text on click.
    document.addEventListener('click', function (e) {
        const el = e.target.closest('.order-notes');
        if (!el) return;
        const expanded = el.classList.toggle('expanded');
        el.textContent = expanded ? el.dataset.full : el.dataset.short;
    });
</script>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://maps.googleapis.com/maps/api/js?key={{config('services.google.api_key')}}&libraries=places"></script>
<script>

    document.addEventListener('DOMContentLoaded', function() {
        let summaryModal = document.getElementById("orderSummaryModal");
        let sidebar = document.querySelector("aside.navbar-vertical");
        let isEditMode = false;
        let customerData = {};
        let validatedDeliveryCost = null;

        // Initialize Select2 for email field
        function initializeEmailSelect2() {
            $('#modal-customer-email').select2({
                dropdownParent: $('#orderSummaryModal'),
                placeholder: 'Search or enter email address',
                allowClear: true,
                tags: true,
                ajax: {
                    url: '{{ route("admin.customers.search") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            page: params.page || 1
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.customers.map(customer => ({
                                id: customer.email,
                                text: `${customer.email} (${customer.name})`,
                                customer: customer
                            })),
                            pagination: {
                                more: data.has_more
                            }
                        };
                    },
                    cache: true
                },
                templateResult: function(customer) {
                    if (customer.loading) return customer.text;
                    if (customer.customer) {
                        return $(`
                        <div class="select2-customer-result">
                            <div class="customer-email">${customer.customer.email}</div>
                            <div class="customer-details text-muted small">
                                ${customer.customer.name} • ${customer.customer.city || 'N/A'}, ${customer.customer.province || 'N/A'}
                            </div>
                        </div>
                    `);
                    }
                    return $(`<div class="select2-new-email">New: ${customer.text}</div>`);
                },
                templateSelection: function(customer) {
                    return customer.customer ? customer.customer.email : customer.text;
                }
            });

            $('#modal-customer-email').on('select2:select', function (e) {
                const data = e.params.data;
                if (data.customer) {
                    populateCustomerFields(data.customer);
                    customerData[data.customer.email] = data.customer;
                } else {
                    clearCustomerFields(data.text);
                }
            });

            $('#modal-customer-email').on('select2:clear', function (e) {
                clearCustomerFields('');
            });
        }

        function populateCustomerFields(customer) {
            document.getElementById('modal-customer-name').value = customer.name || '';
            document.getElementById('modal-customer-phone').value = customer.phone || '';
            document.getElementById('modal-address').value = customer.address || '';
            document.getElementById('modal-city').value = customer.city || '';
            document.getElementById('modal-postal').value = customer.postal_code || '';
            document.getElementById('modal-province').value = customer.province || '';

            const prefilledFields = [
                'modal-customer-name', 'modal-customer-phone', 'modal-address',
                'modal-city', 'modal-postal', 'modal-province'
            ];

            prefilledFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field.value) {
                    field.classList.add('prefilled-field');
                    setTimeout(() => field.classList.remove('prefilled-field'), 2000);
                }
            });
        }

        function clearCustomerFields(emailValue = '') {
            document.getElementById('modal-customer-name').value = '';
            document.getElementById('modal-customer-phone').value = '';
            document.getElementById('modal-address').value = '';
            document.getElementById('modal-city').value = '';
            document.getElementById('modal-postal').value = '';
            document.getElementById('modal-province').value = '';
        }
        function initializeCustomerSelect2() {
            $('#manual-customer-id').select2({
                dropdownParent: $('#orderSummaryModal'),
                placeholder: 'Search customer...',
                allowClear: true,
                width: '100%'
            });
        }

        summaryModal.addEventListener("show.bs.modal", function() {
            if (sidebar) sidebar.style.zIndex = "-1";
            setTimeout(() => {
                initializeEmailSelect2();
            }, 100);
        });

        summaryModal.addEventListener("hidden.bs.modal", function() {
            if (sidebar) sidebar.style.zIndex = "1030";
            if ($('#modal-customer-email').hasClass('select2-hidden-accessible')) {
                $('#modal-customer-email').select2('destroy');
            }
            if($('#manual-customer-id').length){
                $('#manual-customer-id').select2('destroy');
            }
        });

        document.getElementById('create-order-btn').addEventListener('click', function() {
            loadModalContent('create');
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-view')) {
                const orderId = e.target.dataset.orderId;
                const origin = e.target.dataset.origin;
                if(origin == 'online'){
                    loadModalContent('edit', orderId, 'online')
                }else{
                    loadModalContent('edit', orderId);
                }
            }
        });

        function loadModalContent(action, orderId = null, origin = 'manual') {
            let url;
            if (action === 'create') {
                url = '/admin/orders/manual/modal/create';
            }
            else
            { // Editing existing order
                if (origin === 'online') {
                    url = `/admin/orders/modal/${orderId}/edit`; }
                else {
                    url = `/admin/orders/manual/modal/${orderId}/edit`;
                }
            }

            $('#orderSummaryModal .modal-content').html(` <div class="text-center p-5"> <div class="spinner-border text-primary"></div> </div> `);
            $('#orderSummaryModal').modal('show');

            $.get(url).done(function(response) {
                $('#orderSummaryModal .modal-content').html(response);
                setTimeout(() => {
                    if($('#manual-customer-id').length){
                        initializeCustomerSelect2();
                    }
                }, 500);
                }).fail(function(xhr) { $('#orderSummaryModal .modal-content')
                    .html(` <div class="p-4 text-center text-danger"> Failed to load order. </div> `);
                    console.error(xhr);
                });
        }

        function validateForm() {

            const fields = [
                {
                    element: document.getElementById('manual-customer-id'),
                    label: 'Customer'
                },
                {
                    element: document.getElementById('manual-product-id'),
                    label: 'Product Type'
                },
                {
                    element: document.querySelector('input[name="amount"]'),
                    label: 'Amount (lbs/units)'
                },
                {
                    element: document.querySelector('select[name="recurring"]'),
                    label: 'Recurring'
                },
                {
                    element: document.querySelector('input[name="delivery_date"]'),
                    label: 'Date'
                },
                {
                    element: document.querySelector('input[name="delivery_time"]'),
                    label: 'Time'
                }
            ];

            let isValid = true;
            let firstInvalidField = null;
            let missingFields = [];

            // Clear previous validation
            fields.forEach(({ element }) => {
                if (element) {
                    element.classList.remove('is-invalid');
                }
            });

            // Validate regular fields
            fields.forEach(({ element, label }) => {

                if (!element) return;

                const value = element.value?.trim();

                if (!value) {
                    element.classList.add('is-invalid');
                    isValid = false;

                    if (!firstInvalidField) {
                        firstInvalidField = element;
                    }

                    missingFields.push(label);
                }
            });

            // Validate Delivery/Pickup radio
            const pickupDelivery = document.querySelector(
                'input[name="pickup_delivery"]:checked'
            );

            if (!pickupDelivery) {
                isValid = false;
                missingFields.push('Delivery or Pickup');
            }

            // Validate amount > 0
            const amountField = document.querySelector('input[name="amount"]');

            if (
                amountField &&
                (isNaN(amountField.value) || parseFloat(amountField.value) <= 0)
            ) {
                amountField.classList.add('is-invalid');
                isValid = false;

                if (!firstInvalidField) {
                    firstInvalidField = amountField;
                }

                if (!missingFields.includes('Amount (lbs/units)')) {
                    missingFields.push('Amount (must be greater than 0)');
                }
            }

            if (!isValid) {

                if (firstInvalidField) {
                    firstInvalidField.focus();
                }

                Swal.fire({
                    title: 'Validation Error',
                    html:
                        'Please fill in the following required field(s):<br><ul style="text-align:left;">' +
                        missingFields.map(field => `<li>${field}</li>`).join('') +
                        '</ul>',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
            }

            return isValid;
        }

        // Handle Save/Update Button Click
        $(document).on('click','#save-order-btn', async function (e) {
            e.preventDefault();

            if (!validateForm()) return;

            const saveBtn = this;
            const mode = this.dataset.mode;
            const orderId = this.dataset.orderId;
            const form = document.getElementById('order-form');

            // Store original text for reset
            const originalText = saveBtn.innerHTML;

            // Show saving state
            saveBtn.innerHTML = mode === 'edit' ?
                '<i class="la la-spinner la-spin"></i> Updating...' :
                '<i class="la la-spinner la-spin"></i> Creating...';

            // Set form action and method based on mode
            if (mode === 'edit') {
                form.action = `/admin/orders/${orderId}/ajax-update`;
            } else {
                form.action = '/admin/orders/ajax-create';
            }

            // Get form data
            const formData = new FormData(form);

            // Handle delivery date/time combination
            const date = document.getElementById('modal-delivery-date').value;
            const time = document.getElementById('modal-delivery-time').value;
            if (date && time) {
                formData.set('delivery_date', `${date} ${time}:00`);
            } else if (date) {
                formData.set('delivery_date', `${date} 00:00:00`);
            }

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                console.error('CSRF token not found');
                resetButton();
                return;
            }

            // Send AJAX request
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    resetButton();

                    if (data.success) {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(summaryModal);
                        modal.hide();

                        // Show success notification (auto-dismisses, no click required)
                        Swal.fire({
                            title: 'Success!',
                            text: mode === 'edit' ?
                                'Order has been updated successfully' :
                                'Order has been created successfully',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        throw new Error(data.message || 'Operation failed');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resetButton();

                    Swal.fire({
                        title: 'Error!',
                        text: error.message || 'Operation failed. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });

            function resetButton() {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }
        });

        // Handle Delete Button Click
        $(document).on('click', '#delete-pushed-order-btn', function() {
            Swal.fire({
                title: 'Pushed!',
                text: 'This order has already been pushed to delivery service and cannot be deleted.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        });

        $(document).on('click', '#delete-order-btn', function() {
            const orderId = this.dataset.orderId;

            if (!orderId) {
                alert('Order ID not found');
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfToken) {
                        console.error('CSRF token not found');
                        return;
                    }

                    fetch(`order/${orderId}/delete/`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const modal = bootstrap.Modal.getInstance(summaryModal);
                                modal.hide();

                                Swal.fire({
                                    title: 'Deleted!',
                                    text: 'Order has been deleted successfully',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                throw new Error(data.message || 'Delete failed');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting order:', error);
                            Swal.fire({
                                title: 'Error!',
                                text: error.message || 'Failed to delete order. Please try again.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        });
    });

    let currentCustomerAddresses = [];
    let isNewAddress = false;
    let isEditMode = false;
    let hasInlineAddress = false; // Track if we have inline address data

    // Initialize when customer is selected
    function initializeAddressManagement() {
        // Check if we're in edit mode
        isEditMode = $('#current-customer-id').length > 0;

        // Check if inline address exists (for edit mode with no address_id)
        if (isEditMode) {
            const addressId = $('#modal-address-id').val();
            const address = $('#modal-address').val();

            // ✅ If no address_id but address field has value, it's inline address
            hasInlineAddress = !addressId && address && address.trim() !== '';

            console.log('Initialize Address Management', {
                isEditMode: isEditMode,
                addressId: addressId,
                hasInlineAddress: hasInlineAddress,
                address: address
            });
        }

        // Hide address details initially in create mode
        if (!isEditMode) {
            $('#address-details-section').hide();
        }

        // Customer email selection handler (for create mode)
        $('#modal-customer-email').on('select2:select', function (e) {
            const data = e.params.data;

            if (data.customer) {
                // Existing customer selected
                loadCustomerAddresses(data.customer.id);
                enableAddressControls();
            } else {
                // New email address entered (not an existing customer)
                clearAddressSelection();
                enableAddressControls();
                $('#modal-address-select').empty().append('<option value="">No saved addresses - Click "New" to add one</option>');
                $('#address-details-section').hide();
            }
        });

        $('#modal-customer-email').on('select2:clear', function () {
            clearAddressSelection();
            disableAddressControls();
            $('#address-details-section').hide();
        });

        // Address selection handler
        $('#modal-address-select').on('change', function() {
            const addressId = $(this).val();

            if (addressId) {
                // Address selected - show details and populate
                const address = currentCustomerAddresses.find(a => a.id == addressId);
                if (address) {
                    $('#address-details-section').show();
                    populateAddressFields(address);
                    setAddressFieldsReadonly(true);
                    isNewAddress = false;
                    hasInlineAddress = false;
                    $('#save-address-checkbox-container').hide();
                    $('#default-address-checkbox-container').hide();
                }
            } else {
                // ✅ No address selected - only hide if not in new mode or has inline address
                if (!isNewAddress && !hasInlineAddress) {
                    $('#address-details-section').hide();
                }
            }
        });

        // New address button handler
        $('#add-new-address-btn').on('click', function(e) {
            e.preventDefault();

            $('#modal-address-select').val('').trigger('change');
            $('#address-details-section').show();
            clearAddressFields();
            setAddressFieldsReadonly(false);
            isNewAddress = true;
            hasInlineAddress = false;
            $('#save-address-checkbox-container').show();
            $('#default-address-checkbox-container').show();
            $('#modal-address-id').val('');

            setTimeout(() => {
                $('#modal-address-label').focus();
            }, 100);
        });

        // ✅ If in edit mode, load addresses immediately
        if (isEditMode) {
            const customerId = $('#current-customer-id').val();
            const currentAddressId = $('#modal-address-id').val();

            if (customerId) {
                $('#address-details-section').show(); // Always show in edit mode

                // ✅ CRITICAL: If has inline address, keep fields editable
                if (hasInlineAddress) {
                    console.log('Edit mode with inline address - making fields editable');
                    setAddressFieldsReadonly(false);
                    isNewAddress = true;
                    $('#save-address-checkbox-container').show();
                    $('#default-address-checkbox-container').show();
                }

                setTimeout(() => {
                    // ✅ Pass currentAddressId (will be null for inline addresses)
                    loadCustomerAddresses(customerId, currentAddressId);
                    enableAddressControls();
                }, 300);
            }
        }
    }
    // Load addresses for selected customer
    function loadCustomerAddresses(customerId, selectAddressId = null) {
        // ✅ CRITICAL: Store current address field values BEFORE loading
        const currentAddressValues = {
            address_label: $('#modal-address-label').val(),
            location_name: $('#modal-location-name').val(),
            address: $('#modal-address').val(),
            unit: $('#modal-unit').val(),
            city: $('#modal-city').val(),
            postal_code: $('#modal-postal').val(),
            province: $('#modal-province').val(),
            delivery_instructions: $('#modal-delivery-instructions').val()
        };

        // ✅ Check if we have inline address data (address_id is null but fields have values)
        const hasInlineAddressData = !selectAddressId && currentAddressValues.address && currentAddressValues.address.trim() !== '';

        $.ajax({
            url: `/admin/customers/${customerId}/addresses`,
            method: 'GET',
            success: function(response) {
                currentCustomerAddresses = response.addresses;
                populateAddressDropdown(response.addresses);

                // ✅ CRITICAL: Handle different scenarios
                if (selectAddressId) {
                    // SCENARIO 1: We have an address_id - select it
                    $('#modal-address-select').val(selectAddressId).trigger('change');

                } else if (hasInlineAddressData) {
                    // SCENARIO 2: No address_id but we have inline address data - PRESERVE IT
                    console.log('Preserving inline address data');

                    // Clear the dropdown selection
                    $('#modal-address-select').val('').trigger('change');

                    // Restore the inline address values
                    $('#modal-address-label').val(currentAddressValues.address_label);
                    $('#modal-location-name').val(currentAddressValues.location_name);
                    $('#modal-address').val(currentAddressValues.address);
                    $('#modal-unit').val(currentAddressValues.unit);
                    $('#modal-city').val(currentAddressValues.city);
                    $('#modal-postal').val(currentAddressValues.postal_code);
                    $('#modal-province').val(currentAddressValues.province);
                    $('#modal-delivery-instructions').val(currentAddressValues.delivery_instructions);

                    // Keep fields editable and show section
                    setAddressFieldsReadonly(false);
                    $('#address-details-section').show();

                    // Show checkboxes to allow admin to save this address if desired
                    $('#save-address-checkbox-container').show();
                    $('#default-address-checkbox-container').show();
                    isNewAddress = true;
                    hasInlineAddress = true;

                } else if (response.addresses.length === 0) {
                    // SCENARIO 3: No saved addresses and no inline data - trigger new address mode
                    $('#add-new-address-btn').click();

                } else {
                    // SCENARIO 4: Has saved addresses - auto-select default
                    const defaultAddress = response.addresses.find(a => a.is_default);
                    if (defaultAddress) {
                        $('#modal-address-select').val(defaultAddress.id).trigger('change');
                    }
                }
            },
            error: function(xhr) {
                console.error('Failed to load addresses:', xhr);

                if (xhr.status === 404 || xhr.status === 500) {
                    enableAddressControls();
                    $('#modal-address-select').empty().append('<option value="">No saved addresses - Click "New" to add one</option>');

                    // ✅ If we have inline address data, preserve it
                    if (hasInlineAddressData) {
                        console.log('Error loading addresses, but preserving inline address data');

                        // Restore values
                        $('#modal-address-label').val(currentAddressValues.address_label);
                        $('#modal-location-name').val(currentAddressValues.location_name);
                        $('#modal-address').val(currentAddressValues.address);
                        $('#modal-unit').val(currentAddressValues.unit);
                        $('#modal-city').val(currentAddressValues.city);
                        $('#modal-postal').val(currentAddressValues.postal_code);
                        $('#modal-province').val(currentAddressValues.province);
                        $('#modal-delivery-instructions').val(currentAddressValues.delivery_instructions);

                        $('#address-details-section').show();
                        setAddressFieldsReadonly(false);
                        isNewAddress = true;
                        hasInlineAddress = true;
                        $('#save-address-checkbox-container').show();
                        $('#default-address-checkbox-container').show();
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load customer addresses'
                    });
                }
            }
        });
    }

    // Populate address dropdown
    function populateAddressDropdown(addresses) {
        const select = $('#modal-address-select');
        select.empty();

        if (addresses.length === 0) {
            select.append('<option value="">No saved addresses - Click "New" to add one</option>');
            return;
        }

        select.append('<option value="">Select an address...</option>');

        addresses.forEach(address => {
            const label = address.address_label || 'Unnamed Address';
            const details = `${address.address}, ${address.city}, ${address.province} ${address.postal_code}`;
            const defaultBadge = address.is_default ? ' ⭐' : '';

            select.append(`
            <option value="${address.id}" title="${details}">
                ${label}${defaultBadge} - ${address.city}
            </option>
        `);
        });
    }

    // Populate address fields with selected address data
    function populateAddressFields(address) {
        $('#modal-address-id').val(address.id);
        $('#modal-address-label').val(address.address_label || '');
        $('#modal-location-name').val(address.location_name || '');
        $('#modal-address').val(address.address || '');
        $('#modal-unit').val(address.unit || '');
        $('#modal-city').val(address.city || '');
        $('#modal-postal').val(address.postal_code || '');
        $('#modal-province').val(address.province || '');
        $('#modal-country').val(address.country || 'Canada');
        $('#modal-delivery-instructions').val(address.delivery_instructions || '');

        // Highlight prefilled fields
        const addressFields = [
            'modal-address-label', 'modal-location-name', 'modal-address',
            'modal-unit', 'modal-city', 'modal-postal', 'modal-province'
        ];

        addressFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && field.value) {
                field.classList.add('prefilled-field');
                setTimeout(() => field.classList.remove('prefilled-field'), 2000);
            }
        });
    }

    // Clear address fields
    function clearAddressFields() {
        $('#modal-address-id').val('');
        $('#modal-address-label').val('');
        $('#modal-location-name').val('');
        $('#modal-address').val('');
        $('#modal-unit').val('');
        $('#modal-city').val('');
        $('#modal-postal').val('');
        $('#modal-province').val('');
        $('#modal-delivery-instructions').val('');

        // Also remove any validation error classes
        $('#modal-address').removeClass('is-invalid');
        $('#modal-city').removeClass('is-invalid');
        $('#modal-postal').removeClass('is-invalid');
        $('#modal-province').removeClass('is-invalid');
    }

    // Clear address selection
    function clearAddressSelection() {
        currentCustomerAddresses = [];
        $('#modal-address-select').empty().append('<option value="">Select a customer first...</option>');
        clearAddressFields();
        isNewAddress = false;
        hasInlineAddress = false;
    }

    // Enable/disable address controls
    function enableAddressControls() {
        $('#modal-address-select').prop('disabled', false);
        $('#add-new-address-btn').prop('disabled', false);
    }

    function disableAddressControls() {
        $('#modal-address-select').prop('disabled', true);
        $('#add-new-address-btn').prop('disabled', true);
    }

    // Set address fields readonly state
    function setAddressFieldsReadonly(readonly) {
        const fields = [
            'modal-address-label', 'modal-location-name', 'modal-address',
            'modal-unit', 'modal-city', 'modal-postal'
        ];

        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (readonly) {
                    field.setAttribute('readonly', 'readonly');
                    field.classList.add('readonly-field');
                } else {
                    field.removeAttribute('readonly');
                    field.classList.remove('readonly-field');
                }
            }
        });

        // Delivery instructions can always be edited
        $('#modal-delivery-instructions').removeAttr('readonly').removeClass('readonly-field');

        // Handle select differently
        // $('#modal-province').prop(readonly);
    }

    // Enhanced form data collection
    function getEnhancedFormData() {
        const formData = new FormData(document.getElementById('order-form'));

        // Add address management flags
        if (isNewAddress && $('#save-address-checkbox').is(':checked')) {
            formData.append('save_new_address', '1');
            formData.append('set_as_default', $('#default-address-checkbox').is(':checked') ? '1' : '0');
        }

        return formData;
    }

    // Validation enhancement
    function validateAddressSelection() {
        const addressId = $('#modal-address-id').val();

        if (!addressId && !isNewAddress) {
            Swal.fire({
                icon: 'warning',
                title: 'Address Required',
                text: 'Please select an address or create a new one'
            });
            return false;
        }

        // Validate required address fields
        const requiredAddressFields = [
            { id: 'modal-address', label: 'Street Address' },
            { id: 'modal-city', label: 'City' },
            { id: 'modal-postal', label: 'Postal Code' },
            { id: 'modal-province', label: 'Province' }
        ];

        let isValid = true;
        let missingFields = [];

        requiredAddressFields.forEach(({ id, label }) => {
            const field = document.getElementById(id);
            if (!field.value || !field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
                missingFields.push(label);
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Address Information',
                html: 'Please fill in: ' + missingFields.join(', ')
            });
        }

        return isValid;
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for modal to be fully shown
        $('#orderSummaryModal').on('shown.bs.modal', function () {
            initializeAddressManagement();
        });
    });


    function tryPushOrderToNovex(orderId) {


        // Get orderId from modal if not provided as parameter
        if (!orderId) {
            const modalOrderId = document.getElementById('modal-order-id');
            if (modalOrderId && modalOrderId.value) {
                orderId = modalOrderId.value;
            } else {
                console.error('No order ID found');
                return;
            }
        }

        const pushButton = document.querySelector(`#push-btn-${orderId}`);
        const statusLabel = document.querySelector(`#push-status-${orderId}`);

        // Check if elements exist before manipulating them
        if (pushButton) {
            pushButton.disabled = true;
            pushButton.textContent = 'Pushing...';
        }

        fetch(`/orders/${orderId}/push-novex`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Push response:', data);

                if (data.success) {
                    if (statusLabel) {
                        statusLabel.textContent = '✔ Pushed';
                        statusLabel.style.color = 'green';
                    }
                    if (pushButton) {
                        pushButton.style.display = 'none';
                    }
                } else {
                    throw new Error(data.error || 'Push failed');
                }
            })
            .catch(error => {
                console.error('Push error:', error);
                if (statusLabel) {
                    statusLabel.textContent = '⚠ Push Failed';
                    statusLabel.style.color = 'orange';
                }
                if (pushButton) {
                    pushButton.disabled = false;
                    pushButton.textContent = 'Push Order';
                }
            });
    }


    function loadRecurringOrderDetails(action=0, orderId,recurring_id) {
        const url = `/admin/orders/modal/${orderId}/edit?recurring=`+action+`&recurring_id=`+recurring_id;

        // Show loading state
        document.getElementById('modal-content-container').innerHTML = `
                <div class="modal-body text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading...</p>
                </div>`;

        // Show the modal immediately with loading state
        $("#orderSummaryModal").modal('show');

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(html => {
                document.getElementById('modal-content-container').innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading modal content:', error);
                document.getElementById('modal-content-container').innerHTML = `
                    <div class="modal-body text-center">
                        <i class="la la-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Error Loading Content</h5>
                    </div>`;
            });
    }

    function hideOrderDetails(){
        $("#orderSummaryModal").modal('hide');
    }


    document.addEventListener('DOMContentLoaded', function () {

        const invoiceModal     = new bootstrap.Modal(document.getElementById('invoiceModal'));
        const invoiceModalBody = document.getElementById('invoiceModalBody');
        const invoiceLoader    = document.getElementById('invoiceLoader');
        const downloadBtn      = document.getElementById('downloadInvoiceBtn');

        document.querySelectorAll('.view-invoice-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const url        = this.dataset.url;
                const invoiceId  = this.dataset.invoiceId;

                // Reset modal state
                invoiceModalBody.innerHTML = '';
                invoiceLoader.style.display = 'block';
                invoiceModalBody.appendChild(invoiceLoader);
                downloadBtn.href = `/admin/invoice/${invoiceId}/download`; // your PDF download route

                invoiceModal.show();

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                    .then(res => {
                        if (!res.ok) throw new Error('Failed to load invoice');
                        return res.text();
                    })
                    .then(html => {
                        invoiceLoader.style.display = 'none';
                        invoiceModalBody.innerHTML = html;
                    })
                    .catch(err => {
                        invoiceModalBody.innerHTML = `
                    <div class="alert alert-danger m-3">
                        Failed to load invoice. Please try again.
                    </div>`;
                    });
            });
        });

        // Clear modal content when closed to avoid stale data
        document.getElementById('invoiceModal').addEventListener('hidden.bs.modal', function () {
            invoiceModalBody.innerHTML = '';
        });
    });
</script>

@endpush
