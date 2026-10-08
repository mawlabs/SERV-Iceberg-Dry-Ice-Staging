@extends(backpack_view('blank'))

@section('header')
    <section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
        <h1 class="text-capitalize mb-0" bp-section="page-heading">Customer Pricing</h1>
    </section>
@endsection

@section('content')

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Customer</th>
                    <th>Ice Price</th>
                    {{-- <th>Box Price</th> --}}
                    <th>Hazmat Fee</th>
                    <th width="220">Delivery</th>
                    <th>Pickup Fee</th>
                    <th>Other Charges</th>
                    <th width="120">Action</th>
                </tr>
                </thead>

                <tbody>

                @foreach($customers as $customer)

                    @php
                        $pricing = $customer->pricing;
                    @endphp

                    <tr>
                        <form
                            action="{{ route('customer-pricing.update', $customer->id) }}"
                            method="POST">

                            @csrf

                            <td>
                                <div>
                                    <strong>{{ $customer->name }}</strong>
                                    <div class="text-secondary">
                                        {{ $customer->email }}
                                    </div>
                                </div>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    name="ice_price"
                                    class="form-control"
                                    value="{{ $pricing->ice_price ?? 0 }}">
                            </td>

                            {{--
                            <td>
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    name="box_price"
                                    class="form-control"
                                    value="{{ $pricing->box_price ?? 0 }}">
                            </td>
                            --}}

                            <td>
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    name="hazmat_fee"
                                    class="form-control"
                                    value="{{ $pricing->hazmat_fee ?? 0 }}">
                            </td>

                            @php
                                $deliveryMode = $pricing->delivery_mode ?? 'novex';
                            @endphp

                            <td>
                                <div class="d-flex flex-row">
                                    <select name="delivery_mode" class="form-control delivery-mode-select">
                                        <option value="novex" @selected($deliveryMode === 'novex')>Novex rate</option>
                                        <option value="fixed" @selected($deliveryMode === 'fixed')>Fixed rate</option>
                                        <option value="none" @selected($deliveryMode === 'none')>No delivery</option>
                                    </select>
                                    <input
                                        type="number"
                                        step="0.001"
                                        min="0.001"
                                        name="delivery_fee"
                                        class="form-control mt-1 delivery-fee-input"
                                        placeholder="Amount"
                                        value="{{ $deliveryMode === 'fixed' ? $pricing->delivery_fee : '' }}"
                                        @if($deliveryMode !== 'fixed') style="display:none" disabled @endif>
                                </div>
                            </td>

                            <td>
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    name="pickup_fee"
                                    class="form-control"
                                    value="{{ $pricing->pickup_fee ?? 0 }}">
                            </td>

                            <td>
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    name="other_charges"
                                    class="form-control"
                                    value="{{ $pricing->other_charges ?? 0 }}">
                            </td>

                            <td>
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-sm">
                                    Update
                                </button>
                            </td>

                        </form>
                    </tr>

                @endforeach

                </tbody>
            </table>
        </div>

    </div>

@endsection
@section('after_scripts')
    <script>
        // The fixed amount only shows (and is only submitted) in Fixed mode.
        document.querySelectorAll('.delivery-mode-select').forEach(function (select) {
            select.addEventListener('change', function () {
                const amount = select.closest('td').querySelector('.delivery-fee-input');
                const fixed = select.value === 'fixed';
                amount.style.display = fixed ? '' : 'none';
                amount.disabled = !fixed;
                amount.required = fixed;
            });
        });
    </script>
@endsection
@section('after_styles')
    <style>
        table thead tr th {
            color: white;
            text-transform: capitalize;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
@endsection
