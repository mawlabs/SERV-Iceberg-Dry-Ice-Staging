{{-- Order Details Partial View (order_details.blade.php) --}}
<div class="container">
    <div class="row">
        <div class="col-12">
            {{-- Page Header --}}
            <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-center justify-content-between d-print-none">
                <div class="d-flex mb-2 align-items-baseline">
                    <h1 class="text-capitalize mb-0">Order #{{ str_pad($recurring ? $order->recurringOrders->first()->invoice_id : $order->invoice_id, 4, '0', STR_PAD_LEFT) }}</h1>
                    <p class="ms-2 ml-2 mb-0">View your order details and information.</p>
                </div>
                <div>
                    @if(($showBillOfLading ?? false) && $recurring && $order->recurringOrders->first())
                        <a href="{{ route('bill-of-lading.recurring', ['recurringOrder' => $order->recurringOrders->first()->id, 'download' => 1]) }}"
                           class="btn btn-outline-secondary me-2">
                            Bill of Lading
                        </a>
                    @endif
                    <button class="btn btn-secondary btn-submission" onclick="hideOrderDetails()">
                        <i class="la la-arrow-left me-2"></i> Back to Orders
                    </button>
                </div>
            </section>


            <div class="card order-details-card">
                <div class="row">
                    <div class="col-md-12 px-4">
                        {{-- Customer & Order Info --}}
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Invoice Number</label>
                                @if($recurring)
                                    <input type="text" class="form-control" value="{{ $order->recurringOrders->first()->invoice_id }}" readonly>
                                @else
                                    <input type="text" class="form-control" value="{{ $order->invoice_id }}" readonly>
                                @endif
                            </div>

                            <div class="form-group col-md-4">
                                <label>Customer Name</label>
                                <input type="text" class="form-control" value="{{ $order->customer_name }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Email</label>
                                <input type="text" class="form-control" value="{{ $order->email }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Phone</label>
                                <input type="text" class="form-control" value="{{ $order->phone }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Delivery Date</label>
                                @if($recurring)
                                    <input type="text" class="form-control" value="{{ \Carbon\Carbon::parse($order->recurringOrders->first()->scheduled_delivery_date)->format('Y-m-d') }}" readonly>
                                @else
                                    <input type="text" class="form-control" value="{{ \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d') }}" readonly>
                                @endif
                            </div>

                            <div class="form-group col-md-4">
                                <label>Status</label>
                                <div class="form-control d-flex align-items-center" style="background-color: #f8f9fa;">
                                    @php
                                        // Get status from recurring table if it's a recurring order
                                        $orderStatus = $recurring ? $order->recurringOrders->first()->status : $order->status;
                                    @endphp
                                    <span class="badge
                                        @if($orderStatus == 'completed') badge-success
                                        @elseif($orderStatus == 'pending') badge-warning
                                        @elseif($orderStatus == 'cancelled') badge-danger
                                        @else badge-secondary
                                        @endif">
                                        {{ ucfirst($orderStatus) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Delivery Information --}}
                        <h3 class="form-group-heading m-0 mb-4 mt-4">
                            <i class="la la-map-marker me-2"></i> Delivery Information
                            @if ($status)
                                <span class="order-status float-end"><strong>Delivery status:</strong> {{ $status }}</span>
                            @endif
                        </h3>

                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Location Name</label>
                                <input type="text" class="form-control" value="{{ $order->location_name ?? 'N/A' }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Address</label>
                                <input type="text" class="form-control" value="{{ $order->address }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Unit</label>
                                <input type="text" class="form-control" value="{{ $order->unit ?? 'N/A' }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>City</label>
                                <input type="text" class="form-control" value="{{ $order->city }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Province</label>
                                <input type="text" class="form-control" value="{{ $order->province }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Postal Code</label>
                                <input type="text" class="form-control" value="{{ $order->postal_code }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Country</label>
                                <input type="text" class="form-control" value="{{ $order->country }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Pickup/Delivery</label>
                                <input type="text" class="form-control" value="{{ ucfirst($order->pickup_delivery) }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Origin</label>
                                <input type="text" class="form-control" value="{{ $order->origin }}" readonly>
                            </div>
                        </div>

                        {{-- Order Details --}}
                        <h3 class="form-group-heading m-0 mb-4 mt-4">
                            <i class="la la-cube me-2"></i> Order Details
                        </h3>

                        <div class="row">
                            @foreach($order->items as $product)
                                <div class="form-group col-md-4">
                                    <label>{{$product->product->product_name}}</label>
                                    <input type="text" class="form-control" value="{{ $product->amount_of_items }}" readonly>
                                </div>
                            @endforeach

                            <div class="form-group col-md-4">
                                <label>Recurring</label>
                                <div class="form-control d-flex align-items-center" style="background-color: #f8f9fa;">
                                    @if($order->recurring == \App\Models\Order::RECURRING)
                                        <span class="badge badge-info">Yes</span>
                                    @else
                                        <span class="badge badge-secondary">No</span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Hazmat</label>
                                <div class="form-control d-flex align-items-center" style="background-color: #f8f9fa;">
                                    <span class="badge badge-warning">{{$order->hazmat}}</span>
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Payment Status</label>
                                <div class="form-control d-flex align-items-center" style="background-color: #f8f9fa;">
                                    @php
                                        // Get payment status from recurring table if it's a recurring order
                                        $paymentStatus = $recurring
                                            ? $order->recurringOrders->first()->recurring_payment_status
                                            : $order->payment_status;
                                        $isPaid = $paymentStatus == 'paid' || $paymentStatus == 1;
                                    @endphp
                                    <span class="badge {{ $isPaid ? 'bg-success' : 'bg-danger' }}">
                                        {{ $isPaid ? 'PAID' : 'UNPAID' }}
                                    </span>
                                </div>
                            </div>

                            @if($order->notes)
                                <div class="form-group col-md-12">
                                    <label>Notes</label>
                                    <textarea class="form-control" rows="3" readonly>{{ $order->notes }}</textarea>
                                </div>
                            @endif
                        </div>



                        {{-- Cost Breakdown --}}
                        <h3 class="form-group-heading m-0 mb-4 mt-4">
                            <i class="la la-calculator me-2"></i> Cost Breakdown
                        </h3>

                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Sub Total</label>
                                <input type="text" class="form-control" value="${{ number_format($order->sub_total, 2) }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Delivery Cost</label>
                                <input type="text" class="form-control" value="${{ number_format($order->delivery_cost, 2) }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Tax</label>
                                <input type="text" class="form-control" value="${{ number_format($order->tax, 2) }}" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label><strong>Total Cost</strong></label>
                                <input type="text" class="form-control total-cost" value="${{ number_format($order->total_cost, 2) }}" readonly>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="mt-4">
                            <button class="btn btn-secondary btn-submission" onclick="hideOrderDetails()">
                                <i class="la la-arrow-left me-2"></i> Back to Orders
                            </button>

                            @if(($showBillOfLading ?? false) && $recurring && $order->recurringOrders->first())
                                <a href="{{ route('bill-of-lading.recurring', ['recurringOrder' => $order->recurringOrders->first()->id, 'download' => 1]) }}"
                                   class="btn btn-outline-secondary ms-2">
                                    Bill of Lading
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .order-details-card {
        padding: 25px;
        background: white;
        border-radius: 20px;
        margin-top: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: none;
    }

    .order-items-table {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .order-items-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }

    .order-items-table td {
        vertical-align: middle;
    }

    .total-cost {
        font-weight: bold;
        background-color: #e8f5e8 !important;
        color: #155724;
    }

    .badge {
        font-size: 0.875em;
        padding: 0.375rem 0.75rem;
    }

    .form-control[readonly] {
        background-color: #f8f9fa;
        opacity: 1;
    }

    @media print {
        .btn-submission {
            display: none;
        }

        .order-details-card {
            box-shadow: none;
            border: 1px solid #dee2e6;
        }
    }
</style>

<script>
    function hideOrderDetails() {
        const container = document.getElementById('order-details-container');
        const ordersContainer = document.getElementById('orders-list-container');

        container.style.display = 'none';
        ordersContainer.style.display = 'block';
    }
</script>
