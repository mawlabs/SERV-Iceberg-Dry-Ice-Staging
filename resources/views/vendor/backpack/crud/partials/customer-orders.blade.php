{{-- Standing orders, scheduled orders and order history for the customer page --}}
@php
    $statusBadge = function (string $status) {
        return match ($status) {
            'valid', 'open' => ['Scheduled', 'bg-primary'],
            'completed'     => ['Completed', 'bg-success'],
            'cancelled'     => ['Cancelled', 'bg-danger'],
            'skip'          => ['Skipped', 'bg-secondary'],
            default         => [ucfirst($status), 'bg-secondary'],
        };
    };
@endphp

{{-- Standing orders --}}
<div class="card mt-5" id="standing-orders-section">
    <div class="row">
        <div class="col-12 px-4">
            <h3 class="form-group-heading m-0 mb-4">
                <i class="la la-redo-alt me-2"></i> Standing Orders
                <small class="text-muted fs-6">{{ count($standingOrders) }}</small>
            </h3>
            @if(count($standingOrders))
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Every</th>
                            <th>Time</th>
                            <th>Order</th>
                            <th>Pickup / Delivery</th>
                            <th>PO #</th>
                            <th>Next delivery</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($standingOrders as $row)
                            <tr class="{{ $row['active'] ? '' : 'text-muted' }}">
                                <td><strong>{{ $row['day'] }}</strong></td>
                                <td>{{ $row['time'] }}</td>
                                <td>{{ $row['summary'] }}</td>
                                <td class="text-capitalize">{{ $row['method'] }}</td>
                                <td>{{ $row['po'] }}</td>
                                <td>{{ $row['next'] ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $row['active'] ? 'bg-success' : 'bg-secondary' }}">{{ $row['status'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted">No standing orders.</p>
            @endif
        </div>
    </div>
</div>

{{-- Scheduled orders --}}
<div class="card mt-5" id="scheduled-orders-section">
    <div class="row">
        <div class="col-12 px-4">
            <h3 class="form-group-heading m-0 mb-4">
                <i class="la la-calendar-check me-2"></i> Scheduled Orders
                <small class="text-muted fs-6">{{ count($scheduledOrders) }} from today on</small>
            </h3>
            @if(count($scheduledOrders))
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Order</th>
                            <th>Pickup / Delivery</th>
                            <th>PO #</th>
                            <th>Invoice</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($scheduledOrders as $row)
                            <tr>
                                <td>{{ $row['date']->format('Y-m-d') }} <small class="text-muted">{{ $row['date']->format('D g:i A') }}</small></td>
                                <td>{{ $row['type'] }}</td>
                                <td>{{ $row['summary'] }}</td>
                                <td class="text-capitalize">{{ $row['method'] }}</td>
                                <td>{{ $row['po'] }}</td>
                                <td>{{ $row['invoice'] ? '#' . $row['invoice'] : '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted">Nothing scheduled.</p>
            @endif
        </div>
    </div>
</div>

{{-- Order history --}}
<div class="card mt-5" id="order-history-section">
    <div class="row">
        <div class="col-12 px-4">
            <h3 class="form-group-heading m-0 mb-4">
                <i class="la la-history me-2"></i> Order History
                <small class="text-muted fs-6">
                    @if($historyTotal > $historyLimit)
                        latest {{ $historyLimit }} of {{ $historyTotal }}
                    @else
                        {{ $historyTotal }}
                    @endif
                </small>
            </h3>
            @if(count($orderHistory))
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Order</th>
                            <th>Pickup / Delivery</th>
                            <th>PO #</th>
                            <th>Status</th>
                            <th>Invoice</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($orderHistory as $row)
                            @php [$label, $badge] = $statusBadge($row['status']); @endphp
                            <tr>
                                <td>{{ $row['date']->format('Y-m-d') }}</td>
                                <td>{{ $row['type'] }}</td>
                                <td>{{ $row['summary'] }}</td>
                                <td class="text-capitalize">{{ $row['method'] }}</td>
                                <td>{{ $row['po'] }}</td>
                                <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                                <td>{{ $row['invoice'] ? '#' . $row['invoice'] : '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted">No earlier orders.</p>
            @endif
        </div>
    </div>
</div>
