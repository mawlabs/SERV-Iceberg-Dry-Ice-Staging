{{-- This file is used for menu items by any Backpack v6 theme --}}

@php
    $admin = Auth::guard('web')->user();
    $customer = Auth::guard('customer')->user();
@endphp

@if($auth ?? true)

    @if (!$customer)
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

{{--<x-backpack::menu-item title="Users" icon="la la-user" :link="backpack_url('user')" />--}}
{{--<x-backpack::menu-item title="Orders" icon="la la-shopping-cart" :link="backpack_url('orders?all=true')" />--}}

{{--<x-backpack::menu-item title="Orders" icon="la la-shopping-cart" :link="backpack_url('orders')" />--}}

<x-backpack::menu-dropdown title="orders" icon="la la-list">
{{--  <x-backpack::menu-dropdown-item title="Postal Codes" icon="la la-map" :link="backpack_url('postal-codes')" />--}}
  <!-- <x-backpack::menu-dropdown-item title="One-off Orders" icon="la la-shopping-cart" :link="backpack_url('one-off-orders')" /> -->
{{--  <x-backpack::menu-dropdown-item title="Ice Orders" icon="la la-snowflake" :link="backpack_url('ice-orders')" />--}}
    <x-backpack::menu-dropdown-item title="Orders" icon="la la-shopping-cart" :link="backpack_url('orders')" />
    <x-backpack::menu-dropdown-item title="Invoicing" icon="la la-file-invoice-dollar" :link="backpack_url('invoice-generator')" />
    <x-backpack::menu-dropdown-item title="Invoices" icon="la la-list-alt" :link="backpack_url('invoice-generator/list')" />
  <x-backpack::menu-dropdown-item title="Products" icon="la la-product-hunt" :link="backpack_url('product')" />
  <x-backpack::menu-dropdown-item title="Variables" icon="la la-cogs" :link="backpack_url('variables')" />
  <x-backpack::menu-dropdown-item title="Customers" icon="la la-users" :link="backpack_url('customers')" />
  <x-backpack::menu-dropdown-item title="Customer Pricing" icon="la la-dollar" :link="backpack_url('customers-pricing')" />
{{--  <x-backpack::menu-dropdown-item title="Log Files" icon="la la-file-alt" :link="backpack_url('log-files')" />--}}
</x-backpack::menu-dropdown>

<x-backpack::menu-item title="Reports" icon="la la-chart-bar" :link="backpack_url('reports')" />
{{--  <x-backpack::menu-dropdown-item title="Warehouse Sales" icon="la la-dollar-sign" :link="backpack_url('warehouse-sales')" />--}}

<x-backpack::menu-dropdown title="Others" icon="la la-ellipsis-h">
  <x-backpack::menu-dropdown-item title="Manual Payments" icon="la la-money-bill-wave" :link="backpack_url('manual-payments')" />
  <x-backpack::menu-dropdown-item title="Emails" icon="la la-envelope" :link="backpack_url('emails')" />
</x-backpack::menu-dropdown>
    @else
        <li class="nav-item"><a class="nav-link" href="{{ route('customer.dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>

        <li class="nav-item"><a class="nav-link" href="{{ route('customer.orders') }}"><i class="la la-shopping-cart"></i> My Orders</a></li>

    @endif

@endif

