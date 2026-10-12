{{-- resources/views/vendor/backpack/crud/inc/order_modal.blade.php --}}

@php
    // Determine mode
    $isEditMode = $mode === 'edit';

    // Products - handle both create (Product model) and edit (OrderItem model)
    if ($isEditMode) {
        // Edit mode: $products contains OrderItem with relationships
        $productsList = $products;
    } else {
        // Create mode: $products contains Product models
        $productsList = $products;
    }
@endphp

<div class="modal-header border-0">
    <h4 class="modal-title fw-bold">
        <i class="la la-file-invoice mx-2"></i>
        <span>{{ $modalTitle }}</span>
    </h4>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form id="order-form" method="POST">
    @csrf
    <div class="modal-body">
        <div class="row">
            <!-- Order Details Column -->
            <div class="col-md-3">
                <h5><i class="la la-shopping-cart"></i> Order</h5>

                @if($showOrderId)
                    <div class="mb-2">
                        <label class="form-label">INVOICE #</label>
                        <input id="modal-order-id" class="form-control"
                               value="{{ str_pad($defaultValues['invoice_id'], 4, '0', STR_PAD_LEFT) }}" readonly>
                    </div>
                @endif

                <div class="mb-2">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <select id="modal-customer-email" class="form-control" style="width: 100%;"
                            name="email" required {{ $isEditMode ? 'disabled' : '' }}>
                        @if($isEditMode && $defaultValues['customer_email'])
                            <option value="{{ $defaultValues['customer_email'] }}" selected>
                                {{ $defaultValues['customer_email'] }}
                            </option>
                        @endif
                    </select>
                    @if($isEditMode)
                        <input type="hidden" name="email" value="{{ $defaultValues['customer_email'] }}">
                        <input type="hidden" id="current-customer-id" value="{{ $defaultValues['customer_id'] }}">
                    @endif
                </div>

                <div class="mb-2">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input id="modal-customer-name" class="form-control" name="customer_name"
                           value="{{ $defaultValues['customer_name'] }}" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                    <input id="modal-customer-phone" class="form-control" name="phone" type="tel"
                           value="{{ $defaultValues['customer_phone'] }}"
                           pattern="^\+?[0-9]{10}$"
                           oninput="this.value = this.value.replace(/[^\d]/g, '').slice(0, 10)"
                           maxlength="10"
                           placeholder="10 digits only"
                           required>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-bold">Products</label>
                    @if($isEditMode)
                        @foreach($productsList as $item)
                            <div class="row mb-2 align-items-center">
                                <div class="col-12">
                                    <label for="modal-product-{{ $item->product_id }}"
                                           class="form-label label-{{ $item->product_id }}">
                                        {{ $item->product->product_name }} ({{ $item->product->unit }})
                                    </label>
                                </div>
                                <div class="col-12">
                                    <input
                                        id="modal-product-{{ $item->product_id }}"
                                        class="form-control product-amount"
                                        name="products[{{ $item->product_id }}]"
                                        type="number"
                                        min="0"
                                        step="1"
                                        value="{{ $item->amount_of_items ?? 0 }}"
                                        data-product-id="{{ $item->product_id }}"
                                        data-unit-price="{{ $item->unit_price }}">
                                </div>
                            </div>
                        @endforeach
                    @else
                        @foreach($productsList as $product)
                            <div class="row mb-2 align-items-center">
                                <div class="col-12">
                                    <label for="modal-product-{{ $product->id }}"
                                           class="form-label label-{{ $product->id }}">
                                        {{ $product->product_name }} ({{ $product->unit }})
                                    </label>
                                </div>
                                <div class="col-12">
                                    <input
                                        id="modal-product-{{ $product->id }}"
                                        class="form-control product-amount"
                                        name="products[{{ $product->id }}]"
                                        type="number"
                                        min="0"
                                        step="1"
                                        value="0"
                                        data-product-id="{{ $product->id }}"
                                        data-unit-price="{{ $product->price }}">
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="mb-2">
                    <label class="form-label">Recurring <span class="text-danger">*</span></label>
                    <select id="modal-recurring" name="recurring" class="form-select">
                        <option value="non-recurring" {{ $defaultValues['recurring'] === 'non-recurring' ? 'selected' : '' }}>
                            No
                        </option>
                        <option value="recurring" {{ $defaultValues['recurring'] === 'recurring' ? 'selected' : '' }}>
                            Yes
                        </option>
                    </select>
                </div>
            </div>

            <!-- Delivery Details Column -->
            <div class="col-md-5 px-3">
                <h5><i class="la la-truck"></i> Delivery</h5>

                <!-- Address Selection Dropdown -->
                <div class="mb-3">
                    <label class="form-label">Saved Addresses <span class="text-danger">*</span></label>
                    <div class="d-flex gap-2">
                        <select id="modal-address-select" class="form-select" style="flex: 1;"
                            {{ !$isEditMode ? 'disabled' : '' }}>
                            <option value="">{{ $isEditMode ? 'Loading addresses...' : 'Select a customer first...' }}</option>
                        </select>
                        <button type="button" id="add-new-address-btn" class="btn btn-outline-primary"
                            {{ !$isEditMode ? 'disabled' : '' }}>
                            <i class="la la-plus"></i> New
                        </button>
                    </div>
                    <input type="hidden" id="modal-address-id" name="address_id" value="{{ $defaultValues['address_id'] }}">
                </div>

                <!-- Address Details Form -->
                <div id="address-details-section">
                    <div class="mb-2">
                        <label class="form-label">Address Label</label>
                        <input id="modal-address-label" type="text" name="address_label"
                               class="form-control" placeholder="e.g., Main Location, Store #1"
                               maxlength="100" value="{{ $defaultValues['address_label'] }}">
                        <small class="text-muted">Give this address a memorable name</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Location Name</label>
                        <input id="modal-location-name" type="text" name="location_name"
                               class="form-control" placeholder="Business or building name"
                               value="{{ $defaultValues['location_name'] }}">
                    </div>

                    <div class="row mb-2">
                        <div class="col-8 address-field">
                            <label class="form-label">Street Address <span class="text-danger">*</span></label>
                            <input id="modal-address" class="form-control" name="address"
                                   value="{{ $defaultValues['address'] }}" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Unit/Suite</label>
                            <input id="modal-unit" class="form-control" name="unit"
                                   value="{{ $defaultValues['unit'] }}">
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-3 address-field">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <input id="modal-city" type="text" name="city" class="form-control"
                                   value="{{ $defaultValues['city'] }}" required>
                        </div>
                        <div class="col-md-3 address-field">
                            <label class="form-label">Postal <span class="text-danger">*</span></label>
                            <input id="modal-postal" type="text" name="postal_code" class="form-control"
                                   value="{{ $defaultValues['postal_code'] }}" required>
                        </div>
                        <div class="col-md-3 address-field">
                            <label class="form-label">Province <span class="text-danger">*</span></label>
                            <select id="modal-province" name="province" class="form-select" required>
                                <option value="">Select...</option>
                                <option value="BC" {{ $defaultValues['province'] === 'BC' ? 'selected' : '' }}>BC</option>
                                <option value="AB" {{ $defaultValues['province'] === 'AB' ? 'selected' : '' }}>AB</option>
                            </select>
                        </div>
                        <div class="col-md-3 address-field">
                            <label class="form-label">Country</label>
                            <input id="modal-country" name="country" class="form-control"
                                   value="{{ $defaultValues['country'] }}" readonly>
                        </div>
                    </div>

                    <div class="mb-2 d-none">
                        <label class="form-label">Delivery Instructions</label>
                        <textarea id="modal-delivery-instructions" name="delivery_instructions"
                                  class="form-control" rows="2"
                                  placeholder="Special instructions for this address (e.g., back door, after 2pm)">{{ $defaultValues['delivery_instructions'] }}</textarea>
                    </div>

                    <!-- Save Address Checkbox (only for new addresses) -->
                    <div class="mb-2" id="save-address-checkbox-container" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="save-address-checkbox" name="save_new_address" value="1" checked>
                            <label class="form-check-label" for="save-address-checkbox">
                                Save this address to customer profile
                            </label>
                        </div>
                    </div>

                    <!-- Set as Default Checkbox -->
                    <div class="mb-2" id="default-address-checkbox-container" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="default-address-checkbox" name="set_as_default" value="1">
                            <label class="form-check-label" for="default-address-checkbox">
                                Set as default address for this customer
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Hidden delivery selection - always set to delivery -->
                <input type="hidden" id="modal-pickup-or-delivery" name="pickup_delivery" value="delivery">

                <div class="row mb-2 mt-3">
                    <div class="col-8">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="modal-status">
                            <option value="valid" {{ $defaultValues['status'] === 'valid' ? 'selected' : '' }}>Valid</option>
                            <option value="cancelled" {{ $defaultValues['status'] === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-2">
                    @php
                        $today = now()->format('Y-m-d');
                    @endphp
                    <div class="col-md-5">
                        <label class="form-label">Delivery Date <span class="text-danger">*</span></label>
                        <input id="modal-delivery-date" name="delivery_date" type="date"
                               min="{{ $today }}" class="form-control"
                               value="{{ $defaultValues['delivery_date'] }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Delivery Time</label>
                        <input id="modal-delivery-time" name="delivery_time" type="time"
                               class="form-control" value="{{ $defaultValues['delivery_time'] }}">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Order Notes</label>
                    <textarea id="modal-notes" name="notes" class="form-control" rows="2"
                              placeholder="Additional notes for this order">{{ $defaultValues['notes'] }}</textarea>
                    <small class="text-muted">These notes are specific to this order only</small>
                </div>
            </div>

            <!-- Cost Summary Column -->
            <div class="col-md-4">
                <h5><i class="la la-dollar-sign"></i> Cost Summary</h5>
                <div class="p-3 rounded" style="background: rgba(245, 246, 250, 1);">
                    <div class="m-1 cost-summary-products">
                        @if($isEditMode && $productsList->count() > 0)
                            @foreach($productsList as $item)
                                <p class="m-0 d-flex justify-content-between align-items-center">
                                    {{ $item->product->product_name }} ({{ $item->product->unit }})
                                    {{ $item->amount_of_items }} @ ${{ number_format($item->unit_price, 2) }}:
                                    <strong>${{ number_format($item->amount_of_items * $item->unit_price, 2) }}</strong>
                                </p>
                            @endforeach
                        @else
                            <p class="m-0 text-muted">No products added yet</p>
                        @endif
                    </div>

                    <div class="m-1">
                        <p class="m-0 d-flex justify-content-between align-items-center cost-summary-delivery">
                            Delivery:
                            <span class="d-flex align-items-center">
                                <strong class="me-2">${{ number_format($defaultValues['delivery_cost'], 2) }}</strong>
                            </span>
                        </p>
                        <input id="modal-delivery-cost" name="delivery_cost" type="hidden"
                               value="{{ $defaultValues['delivery_cost'] }}">
                    </div>

                    <div class="m-1">
                        <p class="m-0 d-flex justify-content-between align-items-center cost-summary-hazmat">
                            Hazmat:
                            <span class="d-flex align-items-center">
                                <strong class="me-2">${{ number_format($defaultValues['hazmat'], 2) }}</strong>
                            </span>
                        </p>
                        <input id="modal-hazmat-cost" name="hazmat" type="hidden"
                               value="{{ $defaultValues['hazmat'] }}">
                    </div>

                    <hr>

                    <div class="m-1">
                        <p class="m-0 d-flex justify-content-between align-items-center cost-summary-subtotal">
                            Sub-Total:
                            <strong>${{ number_format($defaultValues['sub_total'], 2) }}</strong>
                        </p>
                        <input id="modal-sub-total" name="sub_total" type="hidden"
                               value="{{ $defaultValues['sub_total'] }}">
                    </div>

                    <div class="m-1">
                        <p class="m-0 d-flex justify-content-between align-items-center cost-summary-tax">
                            Tax (15%):
                            <strong>${{ number_format($defaultValues['tax'], 2) }}</strong>
                        </p>
                        <input id="modal-tax" name="tax" type="hidden" value="{{ $defaultValues['tax'] }}">
                    </div>

                    <hr>

                    <div class="m-1">
                        <p class="d-flex justify-content-between align-items-center cost-summary-total m-0">
                            TOTAL:
                            <strong>${{ number_format($defaultValues['total_cost'], 2) }}</strong>
                        </p>
                        <input id="modal-total-cost" name="total_cost" type="hidden"
                               value="{{ $defaultValues['total_cost'] }}">
                    </div>

                    @if($isEditMode)
                        <input type="hidden" name="_method" value="PUT">
                        <input type="hidden" name="origin" value="{{ $defaultValues['origin'] }}">
                    @else
                        <input type="hidden" name="origin" value="manual">
                        <input type="hidden" name="supplier_id" id="supplier_id">
                    @endif
                </div>
            </div>
        </div>
    </div>
</form>

<div class="modal-footer border-0 d-flex justify-content-between">
    <div>
{{--        @if(!$defaultValues['novex_pushed'])--}}
{{--            <button id="save-order-btn" class="btn btn-primary" data-mode="{{ $mode }}"--}}
{{--                    @if($isEditMode) data-order-id="{{ $defaultValues['order_id'] }}" @endif>--}}
{{--                <i class="la la-save"></i> <span>{{ $saveButtonText }}</span>--}}
{{--            </button>--}}
{{--        @endif--}}

        @if($showPushButton && $isEditMode)
            @if(!$defaultValues['novex_pushed'])
                <button id="push-btn-{{ $defaultValues['order_id'] }}"
                        onclick="tryPushOrderToNovex({{ $defaultValues['order_id'] }})"
                        class="btn btn-primary button-push" style="background: gray">
                    Push Order
                </button>
                <span id="push-status-{{ $defaultValues['order_id'] }}" class="ml-2 text-sm text-muted status-push"></span>
            @else
                <button id="push-status-{{ $defaultValues['order_id'] }}" class="ml-2 text-sm btn btn-success status-push">
                    Order Pushed
                </button>
            @endif
        @endif

        @if($isEditMode)
            <a href="{{ route('bill-of-lading.order', ['order' => $defaultValues['order_id'], 'download' => 1]) }}"
               class="btn btn-outline-secondary ms-2">
                Bill of Lading
            </a>
        @endif

        <button class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancel</button>
    </div>

    @if($showDeleteButton && $isEditMode)
        @if($defaultValues['novex_pushed'] == 0)
            <button id="delete-order-btn" class="btn btn-danger" data-order-id="{{ $defaultValues['order_id'] }}">
                <i class="la la-trash"></i> Delete
            </button>
        @else
            <button id="delete-pushed-order-btn" class="btn btn-danger">
                <i class="la la-trash"></i> Delete
            </button>
        @endif
    @endif
</div>

<script>
    // Initialize address management for edit mode
    @if($isEditMode)
    document.addEventListener('DOMContentLoaded', function() {
        const customerId = {{ $defaultValues['customer_id'] ?? 'null' }};
        const currentAddressId = {{ $defaultValues['address_id'] ?? 'null' }};

        console.log('Edit modal initialization', {
            customerId: customerId,
            currentAddressId: currentAddressId,
            hasInlineAddress: !currentAddressId && '{{ $defaultValues['address'] }}' !== ''
        });

        // Load customer addresses after a short delay
        setTimeout(function() {
            if (customerId) {
                // ✅ Pass currentAddressId (null for inline addresses)
                loadCustomerAddresses(customerId, currentAddressId);
                enableAddressControls();
            }
        }, 500);
    });
    @endif
</script>

{{--<div class="modal-header border-0">--}}
{{--    <h4 class="modal-title fw-bold">--}}
{{--        <i class="la la-file-invoice mx-2"></i>--}}
{{--        <span>{{ $modalTitle }}</span>--}}
{{--    </h4>--}}
{{--    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>--}}
{{--</div>--}}
{{--<form id="order-form" method="POST">--}}
{{--    @csrf--}}
{{--    <div class="modal-body">--}}
{{--        <div class="row">--}}
{{--            <!-- Order Details -->--}}
{{--            <div class="col-md-3">--}}
{{--                <h5><i class="la la-shopping-cart"></i> Order</h5>--}}

{{--                @if($showOrderId)--}}
{{--                    <div class="mb-2">--}}
{{--                        <label class="form-label">INVOICE #</label>--}}
{{--                        <input id="modal-order-id" class="form-control" value="{{ str_pad($defaultValues['invoice_id'], 4, '0', STR_PAD_LEFT) }}" readonly>--}}
{{--                    </div>--}}
{{--                @endif--}}

{{--                <div class="mb-2">--}}
{{--                    <label class="form-label">Email <span class="text-danger">*</span></label>--}}
{{--                    <select id="modal-customer-email" class="form-control" style="width: 100%;" name="email" required {{ $mode === 'edit' ? 'disabled' : '' }}>--}}
{{--                        @if($mode === 'edit' && isset($defaultValues['customer_email']))--}}
{{--                            <option value="{{ $defaultValues['customer_email'] }}" selected>{{ $defaultValues['customer_email'] }}</option>--}}
{{--                        @endif--}}
{{--                    </select>--}}
{{--                    @if($mode === 'edit')--}}
{{--                        <input type="hidden" name="email"  value="{{ $defaultValues['customer_email'] }}">--}}
{{--                    @endif--}}
{{--                </div>--}}

{{--                <div class="mb-2">--}}
{{--                    <label class="form-label">Name <span class="text-danger">*</span></label>--}}
{{--                    <input id="modal-customer-name" class="form-control" name="customer_name"  value="{{ $defaultValues['customer_name'] }}" required>--}}
{{--                </div>--}}

{{--                <div class="mb-2">--}}
{{--                    <label class="form-label">Phone<span class="text-danger">*</span></label>--}}
{{--                    <input id="modal-customer-phone" class="form-control" name="phone" type="tel" value="{{ $defaultValues['customer_phone'] }}"--}}
{{--                           pattern="^\+?[0-9]{10}$"--}}
{{--                           oninput="this.value = this.value.replace(/[^\d]/g, '').slice(0, 10)"--}}
{{--                           maxlength="10"--}}
{{--                           placeholder="10 digits only"--}}
{{--                           required>--}}
{{--                </div>--}}

{{--                <div class="mb-2">--}}
{{--                    @if($mode === 'edit')--}}
{{--                        @foreach($products as $product)--}}
{{--                            <div class="row mb-2 align-items-center">--}}
{{--                                <div class="col-12">--}}
{{--                                    <label for="modal-product-{{ $product->product_id }}" class="form-label label-{{ $product->product_id }}">{{ $product->product->product_name }}({{ $product->product->unit }})</label>--}}
{{--                                </div>--}}
{{--                                <div class="col-12">--}}
{{--                                    <input--}}
{{--                                        id="modal-product-{{ $product->product_id }}"--}}
{{--                                        class="form-control product-amount"--}}
{{--                                        name="products[{{ $product->product_id }}]"--}}
{{--                                        type="number"--}}
{{--                                        min="0"--}}
{{--                                        step="1"--}}
{{--                                        value="{{ $product->amount_of_items ?? 0 }}"--}}
{{--                                        data-product-id="{{ $product->product_id }}"--}}
{{--                                        data-unit-price="{{ $product->unit_price }}">--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        @endforeach--}}
{{--                    @else--}}
{{--                        @foreach($products as $product)--}}
{{--                            <div class="row mb-2 align-items-center">--}}
{{--                                <div class="col-12">--}}
{{--                                    <label for="modal-product-{{ $product->id }}" class="form-label label-{{ $product->id }}">{{ $product->product_name }}({{ $product->unit }})</label>--}}
{{--                                </div>--}}
{{--                                <div class="col-12">--}}
{{--                                    <input--}}
{{--                                        id="modal-product-{{ $product->id }}"--}}
{{--                                        class="form-control product-amount"--}}
{{--                                        name="products[{{ $product->id }}]"--}}
{{--                                        type="number"--}}
{{--                                        min="0"--}}
{{--                                        step="1"--}}
{{--                                        value="{{ $defaultValues['products'][$product->id] ?? 0 }}"--}}
{{--                                        data-product-id="{{ $product->id }}"--}}
{{--                                        data-unit-price="{{ $product->price }}">--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        @endforeach--}}
{{--                    @endif--}}
{{--                </div>--}}

{{--                <div class="mb-2">--}}
{{--                    <label class="form-label">Recurring <span class="text-danger">*</span></label>--}}
{{--                    <select id="modal-recurring" name="recurring" class="form-select">--}}
{{--                        <option value="non-recurring" {{ $defaultValues['recurring'] === 'non-recurring' ? 'selected' : '' }}>No</option>--}}
{{--                        <option value="recurring" {{ $defaultValues['recurring'] === 'recurring' ? 'selected' : '' }}>Yes</option>--}}
{{--                    </select>--}}
{{--                </div>--}}
{{--            </div>--}}

{{--            <!-- Delivery Details -->--}}
{{--            <div class="col-md-5 px-3">--}}
{{--                <h5><i class="la la-truck"></i> Delivery</h5>--}}

{{--                <div class="mb-2">--}}
{{--                    <label class="form-label">Location Name</label>--}}
{{--                    <input id="modal-location-name" type="text" name="location_name" class="form-control" value="{{ $defaultValues['location_name'] }}">--}}
{{--                </div>--}}

{{--                <div class="row mb-2">--}}
{{--                    <div class="col-8 address-field">--}}
{{--                        <label class="form-label">Address <span class="text-danger">*</span></label>--}}
{{--                        <input id="modal-address" class="form-control" name="address" value="{{ $defaultValues['address'] }}" required>--}}
{{--                    </div>--}}
{{--                    <div class="col-4">--}}
{{--                        <label class="form-label">Unit</label>--}}
{{--                        <input id="modal-unit" class="form-control" name="unit" value="{{ $defaultValues['unit'] }}">--}}
{{--                    </div>--}}
{{--                </div>--}}

{{--                <div class="row mb-2">--}}
{{--                    <div class="col-md-3 address-field">--}}
{{--                        <label class="form-label">City <span class="text-danger">*</span></label>--}}
{{--                        <input id="modal-city" type="text" name="city" class="form-control" value="{{ $defaultValues['city'] }}" required>--}}
{{--                    </div>--}}
{{--                    <div class="col-md-3 address-field">--}}
{{--                        <label class="form-label">Postal <span class="text-danger">*</span></label>--}}
{{--                        <input id="modal-postal" type="text" name="postal_code" class="form-control" value="{{ $defaultValues['postal_code'] }}" required>--}}
{{--                    </div>--}}
{{--                    <div class="col-md-3 address-field">--}}
{{--                        <label class="form-label">Province <span class="text-danger">*</span></label>--}}
{{--                        <select id="modal-province" name="province" class="form-select" required>--}}
{{--                            <option value="">Select...</option>--}}
{{--                            <option value="BC" {{ $defaultValues['province'] === 'BC' ? 'selected' : '' }}>BC</option>--}}
{{--                            <option value="AB" {{ $defaultValues['province'] === 'AB' ? 'selected' : '' }}>AB</option>--}}
{{--                        </select>--}}
{{--                    </div>--}}
{{--                    <div class="col-md-3 address-field">--}}
{{--                        <label class="form-label">Country</label>--}}
{{--                        <input id="modal-country" name="country" class="form-control" value="{{ $defaultValues['country'] }}" readonly>--}}
{{--                    </div>--}}
{{--                </div>--}}

{{--                <!-- Hidden delivery selection - always set to delivery -->--}}
{{--                <input type="hidden" id="modal-pickup-or-delivery" name="pickup_delivery" value="delivery">--}}

{{--                <div class="row mb-2">--}}
{{--                    <div class="col-8">--}}
{{--                        <label class="form-label">Status</label>--}}
{{--                        <select class="form-select" name="status" id="modal-status">--}}
{{--                            <option value="valid" {{ $defaultValues['status'] === 'valid' ? 'selected' : '' }}>Valid</option>--}}
{{--                            <option value="cancelled" {{ $defaultValues['status'] === 'cancelled' ? 'selected' : '' }}>Cancelled</option>--}}
{{--                        </select>--}}
{{--                    </div>--}}
{{--                </div>--}}

{{--                <div class="row mb-2">--}}
{{--                    @php--}}
{{--                        $today = now()->format('Y-m-d');--}}
{{--                    @endphp--}}
{{--                    <div class="col-md-5">--}}
{{--                        <label class="form-label">Delivery Date<span class="text-danger">*</span></label>--}}
{{--                        <input id="modal-delivery-date" name="delivery_date" type="date" min="{{ $today }}" class="form-control" value="{{ $defaultValues['delivery_date'] }}" required>--}}
{{--                    </div>--}}
{{--                    <div class="col-md-4">--}}
{{--                        <label class="form-label">Delivery Time</label>--}}
{{--                        <input id="modal-delivery-time" name="" type="time" class="form-control" value="{{ $defaultValues['delivery_time'] }}">--}}
{{--                    </div>--}}
{{--                </div>--}}

{{--                <div class="mb-2">--}}
{{--                    <label class="form-label">Notes</label>--}}
{{--                    <textarea id="modal-notes" name="notes" class="form-control" rows="3">{{ $defaultValues['notes'] }}</textarea>--}}
{{--                </div>--}}
{{--            </div>--}}

{{--            <!-- Cost Summary -->--}}
{{--            <div class="col-md-4">--}}
{{--                <h5><i class="la la-dollar-sign"></i> Cost Summary</h5>--}}
{{--                <div class="p-3 rounded" style="background: rgba(245, 246, 250, 1);">--}}
{{--                    <div class="m-1 cost-summary-products">--}}
{{--                        <p class="m-0 d-flex justify-content-between align-items-center">--}}
{{--                        @if($products && $mode === 'edit')--}}
{{--                            @foreach($products as $product)--}}
{{--                                <p class="m-0 d-flex justify-content-between align-items-center">--}}
{{--                                    {{ $product->product->product_name .'('.$product->product->unit.') '.$product->amount_of_items .'@'.$product->unit_price }}--}}
{{--                                    <strong>${{number_format($product->amount_of_items *$product->unit_price,2) }}</strong>--}}
{{--                                </p>--}}
{{--                                @endforeach--}}
{{--                                @endif()--}}
{{--                                </p>--}}
{{--                    </div>--}}
{{--                    <div class="m-1">--}}
{{--                        <p class="m-0 d-flex justify-content-between align-items-center cost-summary-delivery">--}}
{{--                            Delivery:--}}
{{--                            <span class="d-flex align-items-center">--}}
{{--                            <strong class="me-2">${{ number_format($defaultValues['delivery_cost'], 2) }}</strong>--}}
{{--                        </span>--}}
{{--                        </p>--}}
{{--                        <input id="modal-delivery-cost" name="delivery_cost" type="hidden" value="{{ $defaultValues['delivery_cost']??0 }}">--}}
{{--                    </div>--}}
{{--                    <div class="m-1">--}}
{{--                        <p class="m-0 d-flex justify-content-between align-items-center cost-summary-hazmat">--}}
{{--                            Hazmat:--}}
{{--                            <span class="d-flex align-items-center">--}}
{{--                            <strong class="me-2">${{ number_format($defaultValues['hazmat'], 2) }}</strong>--}}
{{--                        </span>--}}
{{--                        </p>--}}
{{--                        <input id="modal-hazmat-cost" name="hazmat" type="hidden" value="{{ $defaultValues['hazmat']??0 }}">--}}
{{--                    </div>--}}
{{--                    <hr>--}}
{{--                    <div class=" m-1">--}}
{{--                        <p class="m-0 d-flex justify-content-between align-items-center cost-summary-subtotal">--}}
{{--                            Sub-Total:--}}
{{--                            <strong>${{ number_format($defaultValues['sub_total'], 2) }}</strong>--}}
{{--                        </p>--}}
{{--                        <input id="modal-sub-total" name="sub_total" type="hidden" value="{{ $defaultValues['sub_total'] }}">--}}
{{--                    </div>--}}
{{--                    <div class="m-1">--}}
{{--                        <p class="m-0 d-flex justify-content-between align-items-center cost-summary-tax">--}}
{{--                            Tax (15%):--}}
{{--                            <strong>${{ number_format($defaultValues['tax'], 2) }}</strong>--}}
{{--                        </p>--}}
{{--                        <input id="modal-tax" name="tax" type="hidden" value="{{ $defaultValues['tax'] }}">--}}
{{--                    </div>--}}
{{--                    <hr>--}}
{{--                    <div class="m-1">--}}
{{--                        <p class="d-flex justify-content-between align-items-center cost-summary-total m-0">--}}
{{--                            TOTAL:--}}
{{--                            <strong>${{ number_format($defaultValues['total_cost'], 2) }}</strong>--}}
{{--                        </p>--}}
{{--                        <input id="modal-total-cost" name="total_cost" type="hidden" value="{{ $defaultValues['total_cost'] }}">--}}
{{--                    </div>--}}
{{--                    @if($mode === 'edit')--}}
{{--                        <input type="hidden" name="_method" value="PUT">--}}
{{--                        <input type="hidden" name="origin" value="{{ $defaultValues['origin'] }}">--}}
{{--                    @else--}}
{{--                        <input type="hidden" name="origin" value="manual">--}}
{{--                        <input type="hidden" name="supplier_id" id="supplier_id">--}}
{{--                    @endif--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--</form>--}}
{{--<div class="modal-footer border-0 d-flex justify-content-between">--}}
{{--    <div>--}}
{{--        @if(!$defaultValues['novex_pushed'])--}}
{{--            <button id="save-order-btn" class="btn btn-primary" data-mode="{{ $mode }}" @if($mode === 'edit') data-order-id="{{ $order->id }}" @endif>--}}
{{--                <i class="la la-save"></i> <span>{{ $saveButtonText }}</span>--}}
{{--            </button>--}}
{{--        @endif--}}

{{--        @if($showPushButton)--}}
{{--            @if(!$defaultValues['novex_pushed'])--}}
{{--                <button id="push-btn-{{ $order->id }}" onclick="tryPushOrderToNovex({{ $order->id }})" class="btn btn-primary button-push" style="background: gray">--}}
{{--                    Push Order--}}
{{--                </button>--}}
{{--                <span id="push-status-{{ $order->id }}" class="ml-2 text-sm text-muted status-push"></span>--}}
{{--            @else--}}
{{--                <button id="push-status-{{ $order->id }}" class="ml-2 text-sm btn btn-success status-push">Order Pushed</button>--}}
{{--            @endif--}}
{{--        @endif--}}

{{--        <button class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancel</button>--}}
{{--    </div>--}}

{{--    @if($showDeleteButton )--}}
{{--        @if( $order->push ===0 )--}}
{{--            <button id="delete-order-btn" class="btn btn-danger" data-order-id="{{ $order->id }}">--}}
{{--                <i class="la la-trash"></i> Delete--}}
{{--            </button>--}}
{{--        @else--}}
{{--            <button id="delete-pushed-order-btn" class="btn btn-danger">--}}
{{--                <i class="la la-trash"></i> Delete--}}
{{--            </button>--}}
{{--        @endif--}}
{{--    @endif--}}
{{--</div>--}}




