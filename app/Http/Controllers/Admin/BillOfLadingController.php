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
        // The weight entered on the order form wins. Otherwise use the dry ice quantity
        // (lb, boxes excluded) converted to kg.
        if ($order->weight_kg !== null) {
            $weightKg = number_format((float) $order->weight_kg, 2, '.', '');
        } else {
            $pounds = $order->items
                ->filter(fn ($item) => preg_match('/^lb/i', (string) ($item->product->unit ?? '')))
                ->sum('amount_of_items');
            $weightKg = number_format($pounds / self::POUNDS_PER_KG, 2, '.', '');
        }
        $invoice ??= Invoice::whereHas('lineItems', fn ($q) => $q->where('order_id', $order->id))
            ->whereNotNull('invoice_number')
            ->latest('id')
            ->first();

        $consignee = $this->consignee($order);
        $pdf = Pdf::loadView('emails.bill-of-lading-pdf', [
            'invoiceNumber'  => $invoice->invoice_number ?? '',
            'date'           => $date ? Carbon::parse($date)->format('F j, Y') : '',
            'customerNumber' => $this->customerOf($order)->legacy_id ?? $this->customerOf($order)->id ?? '',
            'consignee'      => $consignee,

            'weightKg'       => $weightKg,
        ])->setPaper('letter')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('chroot', public_path());

        $filename = 'bill-of-lading-' . $order->id . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * An order-specific consignee takes priority; otherwise the delivery address
     * (the order's own address, falling back to the customer's account address).
     */
    private function consignee(Order $order): array
    {
        $customer = $this->customerOf($order);

        if (filled($order->consignee_name) || filled($order->consignee_address)) {
            return [
                'name'    => $order->consignee_name ?: ($order->location_name ?: ($order->customer_name ?? '')),
                'address' => (string) $order->consignee_address,
                'phone'   => $order->consignee_phone ?: ($order->phone ?? ''),
            ];
        }

        $hasOrderAddress = filled($order->address);
        $parts = $hasOrderAddress
            ? [$order->address, $order->unit ? "Unit {$order->unit}" : null, $order->city, $order->province]
            : [$customer->address ?? null, $customer->city ?? null, $customer->province ?? null];

        return [
            'name'    => $order->location_name ?: ($order->customer_name ?: ($customer->name ?? '')),
            'address' => trim(implode(', ', array_filter($parts))),
            'phone'   => $order->phone ?: ($customer->phone ?? ''),
        ];
    }

    /**
     * The order's customer by id (orders store customer_id); the email-based
     * relation is only a fallback for older orders.
     */
    private function customerOf(Order $order): ?\App\Models\Customer
    {
        return ($order->customer_id ? \App\Models\Customer::find($order->customer_id) : null) ?? $order->customer;
    }
}
