<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Consolidated Invoice</title>
    <style>
        @page { size: letter; margin: 30pt 90pt 30pt 54pt; }
        body {
            margin: 0;
            padding: 0;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #000000;
        }
        table { border-collapse: collapse; }
        p { margin: 0; }
        .fullTable { width: 100%; }
        .addr { font-style: italic; color: #004080; font-size: 10pt; line-height: 1.3; }
        .cust { font-size: 9pt; line-height: 1.9; }
        .items th { font-size: 10pt; font-weight: bold; padding: 4px 3px 6px 3px; white-space: nowrap; border-bottom: 3px solid #000; }
        .items td { font-size: 9pt; padding: 4.5px 3px; white-space: nowrap; }
        .items tr.last td { border-bottom: 3px solid #000; }
        .tot td { font-size: 9pt; padding: 2px 0; }
        .tot .lbl { font-style: italic; font-weight: bold; text-align: right; padding-right: 22px; }
        .tot .val { text-align: right; width: 70px; }
    </style>
</head>
<body>
@php
    // Old design lists the Delivery row before the product row for each date.
    $sortedItems = $lineItems->values()
        ->map(fn ($item, $i) => [$item, $i])
        ->sortBy(fn ($pair) => sprintf('%s-%d-%05d',
            $pair[0]->delivery_date ? \Carbon\Carbon::parse($pair[0]->delivery_date)->format('Ymd') : '00000000',
            strcasecmp(trim($pair[0]->description), 'Delivery') === 0 ? 0 : 1,
            $pair[1]))
        ->map(fn ($pair) => $pair[0])
        ->values();
    $lastIndex = $sortedItems->count() - 1;
    $customerNumber = $customer->legacy_id ?? $customer->id ?? 'N/A';
@endphp

<table class="fullTable">
    <tr>
        <td style="vertical-align: top;">
            <img src="{{ \App\Support\InvoiceAssets::logoDataUri() }}" width="200" alt="logo" />
            <div class="addr" style="margin-top: 14px;">
                329 Churchill Avenue<br>
                New Westminster BC &nbsp;V3L 4P5<br>
                Orders 604-524-0609<br>
                OFFICE 604-524-0601
            </div>
        </td>
        <td style="vertical-align: top; text-align: right;">
            <p style="font-size: 36pt; color: #0000A0; margin-top: 10px;">Invoice</p>
            <p style="font-size: 10pt; font-weight: bold; margin-top: 22px;">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-M-y') }}</p>
            <table style="margin-left: auto; margin-top: 8px;">
                <tr>
                    <td style="font-size: 10pt; text-align: right; padding: 2px 14px 2px 0;">Invoice Number:</td>
                    <td style="font-size: 10pt; font-weight: bold; text-align: right; padding: 2px 0;">{{ $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td style="font-size: 10pt; text-align: right; padding: 2px 14px 2px 0;">Customer Number:</td>
                    <td style="font-size: 10pt; font-weight: bold; text-align: right; padding: 2px 0;">{{ $customerNumber }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="cust" style="margin-top: 6px;">
    <p style="font-size: 11pt; font-weight: bold;">{{ $customer->name ?? 'N/A' }}</p>
    <p>{{ $customer->address ?? '' }}</p>
    <p>{{ $customer->city ?? '' }} {{ $customer->province ?? '' }} &nbsp;&nbsp; {{ $customer->postal_code ?? '' }}</p>
</div>

<table class="fullTable items" style="margin-top: 26px;">
    <colgroup>
        <col style="width: 15%"><col style="width: 16%"><col style="width: 14%"><col style="width: 19%">
        <col style="width: 11%"><col style="width: 13%"><col style="width: 5%"><col style="width: 7%">
    </colgroup>
    <thead>
    <tr>
        <th style="text-align:left;">Date</th>
        <th style="text-align:left;">PO#</th>
        <th style="text-align:right;">Quantity lbs</th>
        <th style="text-align:center;">Item</th>
        <th style="text-align:right;">Price/Unit</th>
        <th style="text-align:right;">Product Cost</th>
        <th style="text-align:right;">GST</th>
        <th style="text-align:right;">PST</th>
    </tr>
    </thead>
    <tbody>
    @foreach($sortedItems as $i => $item)
        @php
            // Unit prices can have 3 decimals; drop a trailing zero so 1.95 stays 1.95 and 1.955 shows in full.
            $unitPrice = number_format($item->unit_price, 3);
            if (substr($unitPrice, -1) === '0') {
                $unitPrice = substr($unitPrice, 0, -1);
            }
        @endphp
        <tr class="{{ $i === $lastIndex ? 'last' : '' }}">
            <td>{{ $item->delivery_date ? \Carbon\Carbon::parse($item->delivery_date)->format('M d, y') : '' }}</td>
            <td>{{ $item->order->po ?? '' }}</td>
            <td style="text-align:right;">{{ $item->quantity }}</td>
            <td style="text-align:center;">{{ $item->description }}</td>
            <td style="text-align:right;">{{ $unitPrice }}</td>
            <td style="text-align:right;">{{ number_format($item->total_price, 2) }}</td>
            <td style="text-align:right;">{{ number_format($item->gst, 2) }}</td>
            <td style="text-align:right;">{{ number_format($item->pst, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p style="font-size: 10pt; font-weight: bold; color: #000080; margin: 18px 0 0 12px;">GST #82379 8541</p>

<table class="tot" style="margin: 14px 0 0 12px; page-break-inside: avoid;">
    <tr>
        <td class="lbl" style="font-size: 10pt;">Total Product Cost</td>
        <td class="val">${{ number_format($subTotal, 2) }}</td>
    </tr>
    @foreach($flatCharges as $charge)
        <tr>
            <td class="lbl">{{ $charge->label ?? \Illuminate\Support\Str::headline($charge->charge_key) }}</td>
            <td class="val">{{ $charge->amount < 0 ? '-' : '' }}${{ number_format(abs($charge->amount), 2) }}</td>
        </tr>
    @endforeach
    <tr>
        <td class="lbl" style="padding-top: 14px;">GST Tax</td>
        <td class="val" style="padding-top: 14px;">${{ number_format($gstTotal, 2) }}</td>
    </tr>
    <tr>
        <td class="lbl">PST Tax</td>
        <td class="val">${{ number_format($pstTotal, 2) }}</td>
    </tr>
    <tr>
        <td class="lbl" style="font-size: 10pt;">Invoice Total</td>
        <td class="val">${{ number_format($totalAmount, 2) }}</td>
    </tr>
</table>

<p style="font-size: 10pt; font-weight: bold; color: #003366; margin-top: 22px;">
    TERMS: &nbsp;Net 15 days, interest of 2% per month (24% per annum) charged on all overdue accounts
</p>
<p style="font-size: 14pt; text-decoration: underline; margin: 8px 0 0 6px;">Notes:</p>
@if(!empty($invoice->notes))
    <p style="font-size: 10pt; white-space: pre-line; margin: 6px 0 0 6px;">{{ $invoice->notes }}</p>
@endif
<p style="font-size: 12pt; color: #0000FF; margin-top: 18px; line-height: 1.3;">
    All totes must be returned within two weeks of delivery unless arrangements have been made with Iceberg. &nbsp;Late fees will apply.
</p>
<p style="font-size: 10pt; font-weight: bold; margin: 4px 0 0 12px;">www.icebergdryice.com</p>

</body>
</html>
