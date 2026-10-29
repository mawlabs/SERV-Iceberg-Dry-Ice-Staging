@extends(backpack_view('blank'))

@php
    $defaultBreadcrumbs = [
      trans('backpack::crud.admin') => url(config('backpack.base.route_prefix'), 'dashboard'),
      $crud->entity_name_plural => url($crud->route),
      trans('backpack::crud.edit') => false,
    ];
@endphp

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">{!! $crud->getHeading() ?? $crud->entity_name_plural !!}</h1>
        <p class="ms-2 ml-2 mb-0" bp-section="page-subheading">Edit Customer</p>
        @if ($crud->hasAccess('list'))
            <p class="mb-0 ms-2 ml-2" bp-section="page-subheading-back-button">
                <small>
                    <a href="{{ url($crud->route) }}" class="d-print-none font-sm">
                        <span><i class="la la-angle-double-left"></i> {{ trans('backpack::crud.back_to_all') }} <span>{{ $crud->entity_name_plural }}</span></span>
                    </a>
                </small>
            </p>
        @endif
    </section>
@endsection

@section('content')

    <div class="row" bp-section="crud-operation-edit">
        <div class="">
            @include('crud::inc.grouped_errors')

            {{-- Customer Information Form --}}
            <form method="post" class="card" id="customer_form"
                  action="{{ route('customer.update', ['id' => $entry->id]) }}">
                {!! csrf_field() !!}

                <div class="row">
                    <div class="col-md-12 px-4">
                        <h3 class="form-group-heading m-0 mb-4">
                            <i class="la la-user me-2"></i> Customer Information
                        </h3>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="name">Name</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="{{ old('name', $entry->name ?? '') }}" placeholder="Name" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="{{ old('email', $entry->email ?? '') }}" placeholder="Email" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       pattern="^\+?[0-9]{10}$"
                                       oninput="this.value = this.value.replace(/[^\d]/g, '').slice(0, 10)"
                                       maxlength="10"
                                       value="{{ old('phone', $entry->phone ?? '') }}" placeholder="10 digits only" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="attention">Attention <small class="text-muted">optional &mdash; person receiving the invoice</small></label>
                                <input type="text" class="form-control" id="attention" name="attention" maxlength="255"
                                       value="{{ old('attention', $entry->attention ?? '') }}" placeholder="Attention">
                            </div>

                            <div class="col-12">
                                <h5 class="mt-3 mb-3"><i class="la la-map-marker"></i> Primary Address</h5>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="address">Address</label>
                                <input type="text" class="form-control" id="address" name="address"
                                       value="{{ old('address', $entry->address ?? '') }}" placeholder="Address" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="city">City</label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="{{ old('city', $entry->city ?? '') }}" placeholder="City" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code"
                                       value="{{ old('postal_code', $entry->postal_code ?? '') }}" placeholder="Postal Code" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="province">Province</label>
                                <select class="form-control" id="province" name="province" required>
                                    <option value="">-- Select Province --</option>
                                    <option value="BC" {{ old('province', $entry->province ?? '') == 'BC' ? 'selected' : '' }}>BC</option>
                                    <option value="AB" {{ old('province', $entry->province ?? '') == 'AB' ? 'selected' : '' }}>AB</option>
                                </select>
                            </div>

                            <div class="form-group d-none col-md-4">
                                <label for="country">Country</label>
                                <input type="text" class="form-control" id="country" name="country"
                                       value="Canada" readonly>
                            </div>
                            @php
                                $supplyLocations = \App\Models\SupplyLocation::where('active', 1)
                                    ->orderBy('name')
                                    ->get();
                            @endphp
                            <div class="form-group col-md-4">
                                <label for="supply_location_id">Supply Location</label>
                                <select class="form-control" id="supply_location_id" name="supply_location_id" required>
                                    <option value="">-- Select Supply Location --</option>

                                    @foreach($supplyLocations as $location)
                                        <option value="{{ $location->id }}"
                                            {{ old('supply_location_id', $entry->supply_location_id ?? '') == $location->id ? 'selected' : '' }}>
                                            {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn-primary btn-submission btn" type="submit">Update</button>
                            <button type="button" class="btn btn-secondary btn-submission mx-2" onclick="window.location.href='/admin/customers'">
                                Close
                            </button>
                            <button type="button" class="btn btn-danger btn-submission float-end" onclick="confirmDelete()">
                                Delete Customer
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <div class="card mt-5">
                <div class="row">
                    <div class="col-12 px-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="form-group-heading m-0">
                                <i class="la la-file-invoice-dollar me-2"></i>  Invoices
                            </h3>
                            <a href="{{route('admin.invoice-generator.index').'?customer='.$entry->id}}" type="button" class="btn btn-primary btn-sm">
                                <i class="la la-plus"></i> Generate New Invoice
                            </a>
                        </div>
                        <div>
                            @if($entry->invoices && $entry->invoices->count() > 0)
                                <table class="table table-bordered table-sm">
                                    <thead>
                                    <tr>
                                        <th>Invoice ID</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($entry->invoices as $invoice)
                                        <tr>
                                            <td>{{$invoice->status == \App\Models\Invoice::STATUS_FINALIZED ? $invoice->invoice_number : 'Draft'}}</td>
                                            <td>{{$invoice->total_amount}}</td>
                                            <td>{{$invoice->payment_status}}</td>
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
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Additional Addresses Section --}}
            <div class="card my-5">
                <div class="row">
                    <div class="col-md-12 px-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="form-group-heading m-0">
                                <i class="la la-map-marked-alt me-2"></i>  Addresses
                            </h3>
                            <button type="button" class="btn btn-primary btn-sm" onclick="showAddAddressModal()">
                                <i class="la la-plus"></i> Add New Address
                            </button>
                        </div>
                        <div id="addresses-container">
                            @if($entry->addresses && $entry->addresses->count() > 0)
                                @foreach($entry->addresses as $address)
                                    <div class="address-card mb-3" data-address-id="{{ $address->id }}">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-center">
                                                    <h5 class="mb-1">
                                                        {{ $address->address_label ?? 'Unnamed Address' }}
                                                        @if($address->is_default)
                                                            <span class="badge bg-success">Default</span>
                                                        @endif
                                                    </h5>
                                                </div>
                                                <p class="mb-1 text-muted">
                                                    {{ $address->address }}
                                                    @if($address->unit), Unit {{ $address->unit }}@endif
                                                </p>
                                                <p class="mb-0 text-muted">
                                                    {{ $address->city }}, {{ $address->province }} {{ $address->postal_code }}
                                                </p>
                                                @if($address->delivery_instructions)
                                                    <p class="mb-0 text-muted small">
                                                        <i class="la la-info-circle"></i> {{ $address->delivery_instructions }}
                                                    </p>
                                                @endif
                                            </div>
                                            <div class="col-md-4 text-end">
                                                @if(!$address->is_default)
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                            onclick="setDefaultAddress({{ $address->id }})">
                                                        <i class="la la-star"></i> Set Default
                                                    </button>
                                                @endif
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        onclick="editAddress({{ $address->id }})">
                                                    <i class="la la-edit"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteAddress({{ $address->id }})">
                                                    <i class="la la-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center py-5" id="no-addresses-message">
                                    <i class="la la-map-marker-alt" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="text-muted">No additional addresses yet. Click "Add New Address" to create one.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Delete Form --}}
            <form id="delete-form" method="POST" action="{{ route('customer.custom_delete', $entry->id) }}" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    {{-- Add/Edit Address Modal --}}
    <div class="modal fade" id="addressModal" tabindex="1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addressModalTitle">Add New Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addressForm">
                        <input type="hidden" id="address_id" value="">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_address_label" class="form-label">Address Label <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_address_label"
                                       placeholder="e.g., Home, Office, Warehouse" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="modal_location_name" class="form-label">Location Name</label>
                                <input type="text" class="form-control" id="modal_location_name"
                                       placeholder="Building or business name">
                            </div>

                            <div class="col-md-8 mb-3">
                                <label for="modal_address" class="form-label">Street Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_address" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="modal_unit" class="form-label">Unit/Suite</label>
                                <input type="text" class="form-control" id="modal_unit">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="modal_city" class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_city" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="modal_postal_code" class="form-label">Postal Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_postal_code" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="modal_province" class="form-label">Province <span class="text-danger">*</span></label>
                                <select class="form-control" id="modal_province" required>
                                    <option value="">Select...</option>
                                    <option value="BC">BC</option>
                                    <option value="AB">AB</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label for="modal_delivery_instructions" class="form-label">Delivery Instructions</label>
                                <textarea class="form-control" id="modal_delivery_instructions" rows="2"
                                          placeholder="Special delivery instructions for this address"></textarea>
                            </div>

                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modal_is_default">
                                    <label class="form-check-label" for="modal_is_default">
                                        Set as default address
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveAddress()">Save Address</button>
                </div>
            </div>
        </div>
    </div>


    {{-- Invoice Modal --}}
    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalLabel">Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body px-5" id="invoiceModalBody">
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

    <style>
        form.card {
            padding: 25px;
            background: white;
            border-radius: 20px;
            margin-top: 15px;
        }

        .card {
            padding: 25px;
            background: white;
            border-radius: 20px;
        }

        h3.form-group-heading {
            font-weight: 800;
            font-size: 24px;
            line-height: 36px;
            letter-spacing: -0.11px;
        }

        .form-control {
            border-radius: 10px !important;
        }

        .btn-submission {
            font-weight: 600;
            font-size: 16px;
            line-height: 20.8px;
            border-radius: 25px;
            padding: 8px 35px;
        }

        .address-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
        }

        .address-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Modal styles - ensure fields are clearly editable */
        #addressModal .form-control,
        #addressModal .form-select {
            background-color: #ffffff !important;
            cursor: text !important;
            pointer-events: auto !important;
        }

        #addressModal .form-control:focus,
        #addressModal .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        #addressModal input[readonly],
        #addressModal select[disabled],
        #addressModal textarea[readonly] {
            background-color: #e9ecef !important;
            cursor: not-allowed !important;
        }
        .modal-backdrop.fade.show {
            z-index: -1;
        }

        footer {
            display: none;
        }
    </style>

@endsection

@push('after_scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const customerId = {{ $entry->id }};
        let currentAddressId = null;
        let addressModal;

        document.addEventListener('DOMContentLoaded', function() {
            addressModal = new bootstrap.Modal(document.getElementById('addressModal'));

            // Customer form submission with validation
            document.querySelector(".btn-submission").addEventListener("click", async function (e) {
                e.preventDefault();

                const address = document.getElementById("address").value.trim();
                const city = document.getElementById("city").value.trim();
                const province = document.getElementById("province").value.trim();
                const postal = document.getElementById("postal_code").value.trim();

                const result = await validateAddress({ address, city, province, postal });

                // if (result.result.verdict.possibleNextAction === "FIX" || result.result.verdict.hasUnconfirmedComponents) {
                //     Swal.fire({
                //         title: 'Address Validation',
                //         html: `Address could not be confirmed. Please provide proper address. <br>Street Address, City, Province, Postal`,
                //         icon: 'warning',
                //         confirmButtonColor: '#d33',
                //     });
                // } else {
                    document.querySelector("#customer_form").submit();
                // }
            });
        });

        async function validateAddress(addressObj) {
            const apiKey = "{{config('services.google.address_api_key')}}";
            const response = await fetch(`https://addressvalidation.googleapis.com/v1:validateAddress?key=${apiKey}`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    address: {
                        regionCode: "CA",
                        addressLines: [addressObj.address],
                        locality: addressObj.city,
                        administrativeArea: addressObj.province,
                        postalCode: addressObj.postal
                    }
                })
            });
            return await response.json();
        }

        function confirmDelete() {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete the customer and all related data.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form').submit();
                }
            });
        }

        function showAddAddressModal() {
            currentAddressId = null;
            document.getElementById('addressModalTitle').textContent = 'Add New Address';
            document.getElementById('addressForm').reset();
            document.getElementById('address_id').value = '';

            // Make sure all fields are editable (remove readonly/disabled)
            document.querySelectorAll('#addressModal input, #addressModal select, #addressModal textarea').forEach(field => {
                field.removeAttribute('readonly');
                field.removeAttribute('disabled');
            });

            addressModal.show();
        }

        function editAddress(addressId) {
            currentAddressId = addressId;
            document.getElementById('addressModalTitle').textContent = 'Edit Address';

            // Load address data
            fetch(`/admin/customers/${customerId}/addresses`)
                .then(r => r.json())
                .then(data => {
                    const address = data.addresses.find(a => a.id === addressId);
                    if (address) {
                        document.getElementById('address_id').value = address.id;
                        document.getElementById('modal_address_label').value = address.address_label || '';
                        document.getElementById('modal_location_name').value = address.location_name || '';
                        document.getElementById('modal_address').value = address.address || '';
                        document.getElementById('modal_unit').value = address.unit || '';
                        document.getElementById('modal_city').value = address.city || '';
                        document.getElementById('modal_postal_code').value = address.postal_code || '';
                        document.getElementById('modal_province').value = address.province || '';
                        document.getElementById('modal_delivery_instructions').value = address.delivery_instructions || '';
                        document.getElementById('modal_is_default').checked = address.is_default;

                        // Make sure all fields are editable (remove readonly)
                        document.querySelectorAll('#addressModal input, #addressModal select, #addressModal textarea').forEach(field => {
                            field.removeAttribute('readonly');
                            field.removeAttribute('disabled');
                        });

                        addressModal.show();
                    }
                })
                .catch(err => {
                    console.error('Error loading address:', err);
                    Swal.fire('Error', 'Failed to load address data', 'error');
                });
        }

        async function saveAddress() {
            const addressData = {
                address_label: document.getElementById('modal_address_label').value,
                location_name: document.getElementById('modal_location_name').value,
                address: document.getElementById('modal_address').value,
                unit: document.getElementById('modal_unit').value,
                city: document.getElementById('modal_city').value,
                postal_code: document.getElementById('modal_postal_code').value,
                province: document.getElementById('modal_province').value,
                delivery_instructions: document.getElementById('modal_delivery_instructions').value,
                is_default: document.getElementById('modal_is_default').checked ? 1 : 0,
            };

            const url = currentAddressId
                ? `/admin/customers/${customerId}/addresses/${currentAddressId}`
                : `/admin/customers/${customerId}/addresses`;

            const method = currentAddressId ? 'PUT' : 'POST';

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(addressData)
                });

                const result = await response.json();

                if (result.success) {
                    addressModal.hide();
                    Swal.fire('Success', result.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error', result.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Failed to save address', 'error');
            }
        }

        function deleteAddress(addressId) {
            Swal.fire({
                title: 'Delete Address?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/admin/customers/${customerId}/addresses/${addressId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        }
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', data.message, 'success').then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'Failed to delete address', 'error');
                        });
                }
            });
        }

        function setDefaultAddress(addressId) {
            fetch(`/admin/customers/${customerId}/addresses/${addressId}/set-default`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', data.message, 'success').then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Failed to set default address', 'error');
                });
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
                    downloadBtn.href = `/admin/invoice-generator/${invoiceId}/pdf`; // your PDF download route

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

        function deleteInvoice(url, label) {
            Swal.fire({
                title: 'Delete invoice?',
                text: `Delete invoice ${label}? Its orders will be released so they can be invoiced again. This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it',
            }).then((result) => {
                if (!result.isConfirmed) return;
                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted', data.message, 'success').then(() => window.location.reload());
                        } else {
                            Swal.fire('Cannot delete', data.message, 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Failed to delete the invoice', 'error'));
            });
        }

        function sendInvoiceEmail(url) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You want to send this incoice to the customer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, send it!',
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`${url}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        }
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Sent!', data.message, 'success').then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'Failed to send an email', 'error');
                        });
                }
            });
        }

        function viewEmailHistory(url) {
            fetch(url, {
                headers: { 'Accept': 'application/json' }
            })
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
                        html: `<table style="width:100%; text-align:left; font-size:13px;">
                            <thead>
                                <tr>
                                    <th style="padding:4px 8px;">When</th>
                                    <th style="padding:4px 8px;">Recipient</th>
                                    <th style="padding:4px 8px;">Status</th>
                                    <th style="padding:4px 8px;">Sent By</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>`,
                        width: 600,
                        confirmButtonText: 'Close'
                    });
                })
                .catch(() => {
                    Swal.fire('Error', 'Could not load send history.', 'error');
                });
        }

    </script>
@endpush
