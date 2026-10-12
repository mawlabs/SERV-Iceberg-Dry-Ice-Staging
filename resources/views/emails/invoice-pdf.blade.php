<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Invoice</title>
    <style>
        @page { margin: 40px 60px; }
        body {
            margin: 0;
            padding: 0;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000000;
        }
        table { border-collapse: collapse; }
        p { margin: 0; }
        .fullTable { width: 100%; }
        .addr { font-style: italic; color: #004080; font-size: 11px; line-height: 1.35; }
        .cust { font-size: 10px; line-height: 1.9; }
        .items th { font-size: 10px; font-weight: bold; padding: 4px 6px 6px 6px; border-bottom: 3px solid #000; }
        .items td { font-size: 10px; padding: 3px 6px; }
        .items tr.last td { border-bottom: 3px solid #000; }
        .tot td { font-size: 10px; padding: 2px 0; }
        .tot .lbl { font-style: italic; font-weight: bold; text-align: right; padding-right: 22px; }
        .tot .val { text-align: right; width: 70px; }
    </style>
</head>
<body class="invoice-body">
<table class="fullTable">
    <tr>
        <td style="vertical-align: top;">
            <img src="{{ \App\Support\InvoiceAssets::logoDataUri() }}" width="190" alt="logo" />
            <div class="addr" style="margin-top: 14px;">
                329 Churchill Avenue<br>
                New Westminster BC &nbsp;V3L 4P5<br>
                Orders 604-524-0609<br>
                OFFICE 604-524-0601
            </div>
        </td>
        <td style="vertical-align: top; text-align: right;">
            <p style="font-size: 36px; color: #0000A0; margin-top: 10px;">Invoice</p>
            <p style="font-size: 11px; font-weight: bold; margin-top: 22px;">{{ \Carbon\Carbon::parse($order->created_at)->format('d-M-y') }}</p>
            <table style="margin-left: auto; margin-top: 8px;">
                <tr>
                    <td style="font-size: 11px; text-align: right; padding: 2px 14px 2px 0;">Invoice Number:</td>
                    <td style="font-size: 11px; font-weight: bold; text-align: right; padding: 2px 0;">{{ $invoice_number }}</td>
                </tr>
                <tr>
                    <td style="font-size: 11px; text-align: right; padding: 2px 14px 2px 0;">Customer Number:</td>
                    <td style="font-size: 11px; font-weight: bold; text-align: right; padding: 2px 0;">{{ $order->customer_name ?? 'N/A' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="cust" style="margin-top: 18px;">
    <p style="font-size: 12px; font-weight: bold;">{{ $order->customer_name ?? 'N/A' }}</p>
    <p>{{ $order->address ?? '' }}</p>
    <p>{{ $order->city ?? '' }} {{ $order->province ?? '' }} &nbsp;&nbsp; {{ $order->postal_code ?? '' }}</p>
</div>

<table class="fullTable items" style="margin-top: 26px;">
    <colgroup>
        <col style="width: 6%"><col style="width: 32%"><col style="width: 14%"><col style="width: 14%">
        <col style="width: 14%"><col style="width: 10%"><col style="width: 10%">
    </colgroup>
    <thead>
    <tr>
        <th style="text-align:left;">#</th>
        <th style="text-align:left;">Item</th>
        <th style="text-align:right;">Unit Price</th>
        <th style="text-align:right;">Quantity</th>
        <th style="text-align:right;">Subtotal</th>
        <th style="text-align:right;">GST</th>
        <th style="text-align:right;">PST</th>
    </tr>
    </thead>
    <tbody>
    @foreach($order->items as $index => $item)
        <tr class="{{ $loop->last ? 'last' : '' }}">
            <td>{{ $index + 1 }}</td>
            <td>{{ $item->product->product_name ?? 'Product' }}</td>
            <td style="text-align:right;">{{ number_format($item->unit_price, 2) }}</td>
            <td style="text-align:right;">{{ $item->amount_of_items }} {{ $item->product->unit }}</td>
            <td style="text-align:right;">{{ number_format($item->total_price, 2) }}</td>
            <td style="text-align:right;">{{ number_format($item->gst, 2) }}</td>
            <td style="text-align:right;">{{ number_format($item->pst, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p style="font-size: 11px; font-weight: bold; color: #000080; margin: 18px 0 0 12px;">GST #82379 8541</p>

<table class="tot" style="margin: 14px 0 0 12px; page-break-inside: avoid;">
    <tr>
        <td class="lbl" style="font-size: 11px;">Subtotal</td>
        <td class="val">${{ number_format($order->sub_total, 2) }}</td>
    </tr>
    @if($order->delivery_cost > 0)
        <tr>
            <td class="lbl">Delivery Cost</td>
            <td class="val">${{ number_format($order->delivery_cost, 2) }}</td>
        </tr>
    @endif
    <tr>
        <td class="lbl" style="padding-top: 14px;">Tax</td>
        <td class="val" style="padding-top: 14px;">${{ number_format($order->tax, 2) }}</td>
    </tr>
    <tr>
        <td class="lbl">Hazmat Fee</td>
        <td class="val">${{ number_format($order->hazmat, 2) }}</td>
    </tr>
    <tr>
        <td class="lbl" style="font-size: 11px;">Total</td>
        <td class="val">${{ number_format($order->total_cost, 2) }}</td>
    </tr>
</table>

<p style="font-size: 11px; font-weight: bold; color: #003366; margin-top: 22px;">
    TERMS: &nbsp;Net 15 days, interest of 2% per month (24% per annum) charged on all overdue accounts
</p>
<p style="font-size: 16px; text-decoration: underline; margin: 8px 0 0 6px;">Notes:</p>
@if(!empty($order->notes))
    <p style="font-size: 11px; white-space: pre-line; margin: 6px 0 0 6px;">{{ $order->notes }}</p>
@endif
<p style="font-size: 14px; color: #0000FF; margin-top: 18px; line-height: 1.3;">
    All totes must be returned within two weeks of delivery unless arrangements have been made with Iceberg. &nbsp;Late fees will apply.
</p>
<p style="font-size: 11px; font-weight: bold; margin: 4px 0 0 12px;">www.icebergdryice.com</p>

</body>
</html>
