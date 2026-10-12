<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\RecurringOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BillOfLadingController extends Controller
{
    const POUNDS_PER_KG = 2.2046;

    /**
     * Bill of lading for a one-off / parent order.
     */
    public function order(Request $request, $orderId)
    {
        $order = Order::with(['items.product', 'customer'])->findOrFail($orderId);

        return $this->render($request, $order, $order->delivery_date, $order->invoice);
    }

    /**
     * Bill of lading for one occurrence of a recurring order. Items come from the parent
     * order; the date is the scheduled delivery date of the occurrence.
     */
    public function recurring(Request $request, $recurringOrderId)
    {
        $recurring = RecurringOrder::with(['order.items.product', 'order.customer', 'invoice'])->findOrFail($recurringOrderId);

        return $this->render($request, $recurring->order, $recurring->scheduled_delivery_date, $recurring->invoice);
    }

    private function render(Request $request, Order $order, $date, ?Invoice $invoice)
    {
        // Order quantities are in lbs; the bill of lading is in kg. Boxes are not shipped weight.
        $pounds = $order->items
            ->filter(fn ($item) => preg_match('/^lb/i', (string) ($item->product->unit ?? '')))
            ->sum('amount_of_items');
        $weightKg = number_format($pounds / self::POUNDS_PER_KG, 2, '.', '');

        $invoice ??= Invoice::whereHas('lineItems', fn ($q) => $q->where('order_id', $order->id))
            ->whereNotNull('invoice_number')
            ->latest('id')
            ->first();

        $address = trim(implode(', ', array_filter([
            $order->address,
            $order->unit ? "Unit {$order->unit}" : null,
            $order->city,
            $order->province,
        ])));

        $pdf = Pdf::loadView('emails.bill-of-lading-pdf', [
            'invoiceNumber'  => $invoice->invoice_number ?? '',
            'date'           => $date ? Carbon::parse($date)->format('M d Y') : '',
            'customerNumber' => $order->customer->legacy_id ?? $order->customer->id ?? '',
            'consignee'      => [
                'name'    => $order->location_name ?: ($order->customer_name ?? ''),
                'address' => $address,
                'phone'   => $order->phone ?? '',
            ],
            'weightKg'       => $weightKg,
            'signerName'     => $request->query('signer', backpack_user()->name ?? ''),
        ])->setPaper('letter')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('chroot', public_path());

        return $pdf->stream('bill-of-lading-' . $order->id . '.pdf');
    }
}
