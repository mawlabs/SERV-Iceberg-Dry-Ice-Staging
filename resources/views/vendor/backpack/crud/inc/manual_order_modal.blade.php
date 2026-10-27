@php
    $isEditMode = $mode === 'edit';
    $locked = $lockedByDraftInvoice ?? false;
@endphp

<div class="modal-header border-0">
    <h4 class="modal-title fw-bold">
        <i class="la la-shopping-cart mx-2"></i>
        {{ $modalTitle }}
    </h4>
    <button type="button"
            class="btn-close"
            data-bs-dismiss="modal">
    </button>

</div>

<form id="order-form" method="POST">
    @csrf
    @if($isEditMode)
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="order_id" value="{{ $order->id }}">
    @endif

    <input type="hidden" name="origin" value="manual">

    <div class="modal-body">
        @if($locked)
            <div class="alert alert-warning">
                This order is part of a draft invoice, so it can't be edited. Discard the draft invoice to make changes.
            </div>
        @endif
        <fieldset @disabled($locked)>
        <div class="row">
            <div class="col-12">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Customer
                    </label>

                    <select
                        id="manual-customer-id"
                        name="customer_id"
                        class="form-select"
                        required>

                        <option value="">Select Customer</option>

                        @foreach($customers as $customer)
                            <option
                                value="{{ $customer->id }}"
                                @selected(old('customer_id', $defaultValues['customer_id'] ?? null) == $customer->id)>
                                {{ $customer->name }} ({{ $customer->email }})
                            </option>
                        @endforeach

                    </select>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Product Type
                    </label>

                    <select
                        id="manual-product-id"
                        name="product"
                        class="form-select"
                        required>

                        <option value="">Select Product</option>

                        @foreach($products as $product)
                            <option
                                value="{{ $product->id }}"
                                @selected(old('product_id', $defaultValues['product_id'] ?? null) == $product->id)>
                                {{ $product->product_name }}
                            </option>
                        @endforeach

                    </select>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Amount (lbs/boxes)
                    </label>

                    <input
                        type="number"
                        min="1"
                        step="1"
                        class="form-control"
                        name="amount"
                        value="{{ old('amount', $defaultValues['amount'] ?? 1) }}"
                        required>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label fw-bold d-block">
                        Order Type
                    </label>

                    <div class="form-check form-check-inline">
                        <input
                            class="form-check-input"
                            type="radio"
                            name="pickup_delivery"
                            value="delivery"
                            id="delivery-option"
                            {{ ($defaultValues['pickup_delivery'] ?? 'delivery') == 'delivery' ? 'checked' : '' }}>

                        <label class="form-check-label" for="delivery-option">
                            Delivery
                        </label>
                    </div>

                    <div class="form-check form-check-inline">
                        <input
                            class="form-check-input"
                            type="radio"
                            name="pickup_delivery"
                            value="pickup"
                            id="pickup-option"
                            {{ ($defaultValues['pickup_delivery'] ?? '') == 'pickup' ? 'checked' : '' }}>

                        <label class="form-check-label" for="pickup-option">
                            Pickup
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        PO #
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        name="po_number"
                        value="{{ old('po_number', $defaultValues['po_number'] ?? '') }}">
                </div>
            </div>
            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Recurring
                    </label>

                    <select
                        class="form-select"
                        name="recurring">

                        <option value="non-recurring"
                            {{ ($defaultValues['recurring'] ?? '') == 'non-recurring' ? 'selected' : '' }}>
                            No
                        </option>

                        <option value="recurring"
                            {{ ($defaultValues['recurring'] ?? '') == 'recurring' ? 'selected' : '' }}>
                            Yes
                        </option>

                    </select>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Date
                    </label>

                    <input
                        min="{{\Illuminate\Support\Carbon::now()->format('Y-m-d')}}"
                        type="date"
                        class="form-control"
                        id="modal-delivery-date"
                        name="delivery_date"
                        value="{{ old('delivery_date', $defaultValues['delivery_date'] ?? '') }}"
                        required>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Time
                    </label>

                    <input
                        min="{{\Illuminate\Support\Carbon::now()->format('h:m')}}"
                        type="time"
                        class="form-control"
                        id="modal-delivery-time"
                        name="delivery_time"
                        value="{{ old('delivery_time', $defaultValues['delivery_time'] ?? '') }}">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Dry Ice Weight (lb)
                    </label>

                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control"
                        id="weight-lb"
                        name="weight_lb"
                        value="{{ old('weight_lb', $defaultValues['weight_lb'] ?? '') }}">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Dry Ice Weight (kg) <small class="text-muted fw-normal">printed on the bill of lading</small>
                    </label>

                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control"
                        id="weight-kg"
                        name="weight_kg"
                        value="{{ old('weight_kg', $defaultValues['weight_kg'] ?? '') }}">
                </div>
            </div>
            <div class="col-12">
                <div class="mb-1">
                    <label class="form-label fw-bold">
                        Consignee <small class="text-muted fw-normal">optional &mdash; leave blank to use the delivery address on the bill of lading</small>
                    </label>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="mb-3">
                    <input
                        type="text"
                        class="form-control"
                        name="consignee_name"
                        maxlength="255"
                        placeholder="Name"
                        value="{{ old('consignee_name', $defaultValues['consignee_name'] ?? '') }}">
                </div>
            </div>
            <div class="col-lg-5">
                <div class="mb-3">
                    <input
                        type="text"
                        class="form-control"
                        name="consignee_address"
                        maxlength="255"
                        placeholder="Address"
                        value="{{ old('consignee_address', $defaultValues['consignee_address'] ?? '') }}">
                </div>
            </div>
            <div class="col-lg-3">
                <div class="mb-3">
                    <input
                        type="text"
                        class="form-control"
                        name="consignee_phone"
                        maxlength="50"
                        placeholder="Phone"
                        value="{{ old('consignee_phone', $defaultValues['consignee_phone'] ?? '') }}">
                </div>
            </div>            @if($isEditMode)
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Status
                            @if($order->recurring === \App\Models\Order::RECURRING)
                                <small class="text-muted fw-normal">cancelling cancels the whole recurring series</small>
                            @endif
                        </label>

                        @php $statusLocked = $statusLocked ?? false; @endphp
                        <select class="form-select" name="status" @disabled($statusLocked)>
                            <option value="valid" @selected($order->status === 'valid')>Valid</option>
                            <option value="cancelled" @selected($order->status === 'cancelled')>Cancelled</option>
                            @if(in_array($order->status, ['completed', 'skip']))
                                <option value="{{ $order->status }}" selected>{{ ucfirst($order->status) }}</option>
                            @endif
                        </select>
                        @if($statusLocked)
                            <small class="text-muted">This order is on an invoice, so its status can't be changed.</small>
                        @endif
                    </div>
                </div>
            @endif
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Notes
                    </label>

                    <textarea
                        class="form-control"
                        rows="4"
                        name="notes">{{ old('notes', $defaultValues['notes'] ?? '') }}</textarea>
                </div>
            </div>
        </div>
        </fieldset>

    </div>

    <div class="modal-footer border-0">

        <button
            type="button"
            class="btn btn-secondary"
            data-bs-dismiss="modal">
            Cancel
        </button>
        @if((isset($order) && $order->payment_status !== 'paid' || !isset($order)) && !$locked)
            <button
                type="button"
                id="save-order-btn"
                class="btn btn-primary"
                data-mode="{{ $mode }}"
                @if($isEditMode)
                    data-order-id="{{ $order->id }}"
                @endif>
                {{ $saveButtonText }}
            </button>
        @endif

        @if(isset($order) && $order->status == 'completed' || isset($order) && $order->payment_status == 'paid')
            @if(!$order->push)
                <button id="push-btn-{{ $order->id }}"
                        onclick="tryPushOrderToNovex({{ $order->id }})"
                        class="btn btn-primary button-push" style="background: gray">
                    Push Order
                </button>
                <span id="push-status-{{ $order->id }}" class="ml-2 text-sm text-muted status-push"></span>
            @else
                <button id="push-status-{{ $order->id }}" class="ml-2 text-sm btn btn-success status-push">
                    Order Pushed
                </button>
            @endif
        @endif

        @if($isEditMode)
            <a href="{{ route('bill-of-lading.order', ['order' => $order->id, 'download' => 1]) }}"
               class="btn btn-outline-secondary">
                Bill of Lading
            </a>
        @endif

        @if($isEditMode)
            <button
                type="button"
                id="delete-order-btn"
                class="btn btn-danger"
                data-order-id="{{ $order->id }}">
                Delete
            </button>
        @endif

    </div>
</form>

<script>
    // Keep the dry ice weight in lb and kg in step. Until the weight is typed in
    // by hand it simply follows the order amount.
    (function () {
        const LB_PER_KG = 2.2046;
        const $ = window.jQuery;
        if (!$) return;

        const round2 = (n) => (Math.round(n * 100) / 100).toFixed(2);
        // Follow the order amount when no weight is saved yet, or when the saved weight
        // is just the amount (so editing the amount later keeps them in step).
        const savedLb = parseFloat($('#weight-lb').val());
        const amountNow = parseFloat($('input[name="amount"]').val());
        const state = { auto: isNaN(savedLb) || (!isNaN(amountNow) && Math.abs(savedLb - amountNow) < 0.01) };

        function followAmount() {
            const amount = parseFloat($('input[name="amount"]').val());
            if (!state.auto) return;
            if (isNaN(amount)) { $('#weight-lb, #weight-kg').val(''); return; }
            $('#weight-lb').val(round2(amount));
            $('#weight-kg').val(round2(amount / LB_PER_KG));
        }

        $('#weight-lb').off('input.bol').on('input.bol', function () {
            state.auto = false;
            const lb = parseFloat(this.value);
            $('#weight-kg').val(isNaN(lb) ? '' : round2(lb / LB_PER_KG));
        });
        $('#weight-kg').off('input.bol').on('input.bol', function () {
            state.auto = false;
            const kg = parseFloat(this.value);
            $('#weight-lb').val(isNaN(kg) ? '' : round2(kg * LB_PER_KG));
        });
        $('input[name="amount"]').off('input.bol').on('input.bol', followAmount);

        followAmount();
    })();
</script>