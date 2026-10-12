<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPricing;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\InvoiceFlatCharges;
use App\Models\InvoiceLineItems;
use App\Models\InvoiceOrders;
use App\Models\Order;
use App\Models\RecurringOrder;
use App\Mail\ConsolidatedInvoiceMail;
use App\Services\NovexQuoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvoiceGeneratorController extends Controller
{
    public function index()
    {
        $customers = Customer::orderBy('name')->get();

        // Unfinalized drafts the admin can resume / discard.
        $drafts = Invoice::draft()
            ->with('customer')
            ->orderByDesc('id')
            ->get();

        // When arriving from an order-list "Generate Invoice" click (?draft=<id>),
        // auto-open that draft in the editor.
        $openDraftId = null;
        if (request()->filled('draft')) {
            $openDraftId = optional(Invoice::draft()->find(request('draft')))->id;
        }

        return view('vendor.backpack.crud.Invoice.customer-invoice', [
            'customers'   => $customers,
            'drafts'      => $drafts,
            'openDraftId' => $openDraftId,
        ]);
    }

    /**
     * Step 1: Admin selects customer + date range.
     * Pulls eligible orders/recurring orders, applies customer pricing,
     * builds a draft structure, and stores it (currently in session).
     */
    public function buildDraft(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
        ]);

        $customerId = $request->customer_id;
        $startDate  = $request->start_date;
        $endDate    = $request->end_date;

        $pricing = CustomerPricing::where('customer_id', $customerId)->first();
        $usingDefaultPricing = false;

        if (!$pricing) {
            $usingDefaultPricing = true;
        }

        // Orders already reserved by ANY invoice (draft or finalized) via the
        // invoice_orders pivot must not be pulled into a second draft.
        $claimedOrderIds = InvoiceOrders::where('invoiceable_type', Order::class)
            ->pluck('invoiceable_id')->all();
        $claimedRecurringIds = InvoiceOrders::where('invoiceable_type', RecurringOrder::class)
            ->pluck('invoiceable_id')->all();

        // ---- One-time orders ----
        $orders = Order::where('customer_id', $customerId)
            ->where('origin', 'manual')
            ->whereIn('status', ['valid', 'completed'])
            ->where(function ($q) {
                $q->whereNull('payment_status')->orWhere('payment_status', 'unpaid');
            })
            ->whereNull('invoice_id')
            ->whereNotIn('id', $claimedOrderIds)
            ->whereBetween('delivery_date', [$startDate, $endDate . ' 23:59:59'])
            ->with(['items.product'])
            ->get();

        // ---- Recurring orders ----
        $recurringOrders = RecurringOrder::whereIn('status', ['open', 'completed'])
            ->whereNull('recurring_payment_status')
            ->whereNull('invoice_id')
            ->whereNotIn('id', $claimedRecurringIds)
            ->whereBetween('scheduled_delivery_date', [$startDate, $endDate])
            ->whereHas('order', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)->where('origin', 'manual');
            })
            ->with(['order.items.product'])
            ->get();

        $invoice = $this->createDraftFromOrders($customerId, $orders, $recurringOrders, $pricing);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible orders found for this customer in the selected range.',
            ], 422);
        }

        return response()->json([
            'success'               => true,
            'draft'                 => $this->serializeDraft($invoice->fresh()),
            'using_default_pricing' => $usingDefaultPricing,
        ]);
    }

    /**
     * Build + persist a draft invoice from the given orders / recurring orders,
     * applying customer pricing, once-per-invoice fees, and reserving each order
     * via invoice_orders. Returns null if there is nothing invoiceable.
     * Shared by buildDraft (customer + date range) and buildDraftFromOrder.
     */
    private function createDraftFromOrders($customerId, $orders, $recurringOrders, $pricing): ?Invoice
    {
        $lineItems = [];
        $orderRefs = [];
        $customer  = Customer::with('supplyLocation')->find($customerId);

        foreach ($orders as $order) {
            $deliveryDate = $order->delivery_date;

            foreach ($order->items as $item) {
                $lineItems[] = $this->buildLineItem($item, $pricing, $order->id, $deliveryDate);
            }
            foreach ($this->buildFeeLineItems($pricing, $customer, $order, $deliveryDate) as $fee) {
                $lineItems[] = $fee;
            }

            $orderRefs[] = [
                'invoiceable_type' => Order::class,
                'invoiceable_id'   => $order->id,
                'order_type'       => 'one_time',
            ];
        }

        foreach ($recurringOrders as $recurring) {
            $parentOrder = $recurring->order;
            if (!$parentOrder) {
                continue;
            }
            $deliveryDate = $recurring->scheduled_delivery_date;

            foreach ($parentOrder->items as $item) {
                $lineItems[] = $this->buildLineItem($item, $pricing, $parentOrder->id, $deliveryDate);
            }
            foreach ($this->buildFeeLineItems($pricing, $customer, $parentOrder, $deliveryDate) as $fee) {
                $lineItems[] = $fee;
            }

            $orderRefs[] = [
                'invoiceable_type' => RecurringOrder::class,
                'invoiceable_id'   => $recurring->id,
                'order_type'       => 'recurring',
            ];
        }

        if (empty($orderRefs)) {
            return null;
        }

        // Only "other charges" remains an invoice-level flat charge. Hazmat,
        // delivery and pickup fees are now per-order dated line items (see
        // buildFeeLineItems), matching the reference invoice's one-line-per-
        // delivery format. GST/PST are computed live per line, not stored here.
        $flatCharges = [];
        if ($pricing && !empty($pricing->other_charges) && (float) $pricing->other_charges != 0) {
            $flatCharges[] = ['charge_key' => 'other_charges', 'label' => null, 'amount' => (float) $pricing->other_charges];
        }

        return DB::transaction(function () use ($customerId, $orderRefs, $lineItems, $flatCharges) {
            $invoice = Invoice::create([
                'status'             => Invoice::STATUS_DRAFT,
                'invoice_number'     => null,
                'invoice_type'       => 'one_time',
                'total_amount'       => 0,
                'payment_status'     => 'pending',
                'parent_invoice_id'  => null,
                'recurring_sequence' => 1,
                'invoice_date'       => now()->format('Y-m-d'),
                'customer_id'        => $customerId,
            ]);

            foreach ($orderRefs as $ref) {
                InvoiceOrders::create([
                    'invoice_id'       => $invoice->id,
                    'invoiceable_type' => $ref['invoiceable_type'],
                    'invoiceable_id'   => $ref['invoiceable_id'],
                    'order_type'       => $ref['order_type'],
                ]);
            }

            foreach ($lineItems as $li) {
                InvoiceLineItems::create([
                    'invoice_id'    => $invoice->id,
                    'order_id'      => $li['order_id'],
                    'product_id'    => $li['product_id'],
                    'description'   => $li['description'],
                    'quantity'      => $li['quantity'],
                    'unit_price'    => $li['unit_price'],
                    'total_price'   => $li['total_price'],
                    'delivery_date' => $li['delivery_date'] ?? null,
                ]);
            }

            foreach ($flatCharges as $fc) {
                InvoiceFlatCharges::create([
                    'invoice_id' => $invoice->id,
                    'charge_key' => $fc['charge_key'],
                    'label'      => $fc['label'],
                    'amount'     => $fc['amount'],
                ]);
            }

            $this->recomputeTotal($invoice);

            return $invoice;
        });
    }

    /**
     * One-click from the order list: build a draft invoice from a single order
     * (or recurring instance) and land the admin in the editor. Account-holder
     * (manual) orders only. If the order is already reserved in a draft, redirect
     * to that draft instead of creating a duplicate.
     */
    public function buildDraftFromOrder(Request $request)
    {
        $request->validate([
            'order_id'     => 'required|integer',
            'is_recurring' => 'nullable|boolean',
        ]);

        $isRecurring = (bool) $request->input('is_recurring', false);

        if ($isRecurring) {
            $recurring = RecurringOrder::with('order.items.product')->find($request->order_id);

            if (!$recurring || !$recurring->order || $recurring->order->origin !== 'manual') {
                return back()->with('error', 'This recurring order cannot be invoiced here.');
            }
            if (!in_array($recurring->status, ['open', 'completed'])) {
                return back()->with('error', 'This recurring order is not eligible for invoicing (status: ' . $recurring->status . ').');
            }
            if (!is_null($recurring->recurring_payment_status)) {
                return back()->with('error', 'This recurring order is already paid.');
            }
            if (!is_null($recurring->invoice_id)) {
                return back()->with('error', 'This recurring order is already invoiced.');
            }

            $existing = InvoiceOrders::where('invoiceable_type', RecurringOrder::class)
                ->where('invoiceable_id', $recurring->id)->first();
            if ($existing) {
                return redirect()->route('admin.invoice-generator.index', ['draft' => $existing->invoice_id]);
            }

            $customerId = $recurring->order->customer_id;
            $pricing    = CustomerPricing::where('customer_id', $customerId)->first();
            $invoice    = $this->createDraftFromOrders($customerId, collect(), collect([$recurring]), $pricing);
        } else {
            $order = Order::with('items.product')->find($request->order_id);

            if (!$order || $order->origin !== 'manual') {
                return back()->with('error', 'Only account-holder orders can be invoiced here.');
            }
            if (!in_array($order->status, ['valid', 'completed'])) {
                return back()->with('error', 'This order is not eligible for invoicing (status: ' . $order->status . ').');
            }
            if ($order->payment_status && $order->payment_status !== 'unpaid') {
                return back()->with('error', 'This order is already paid.');
            }
            if (!is_null($order->invoice_id)) {
                return back()->with('error', 'This order is already invoiced.');
            }

            $existing = InvoiceOrders::where('invoiceable_type', Order::class)
                ->where('invoiceable_id', $order->id)->first();
            if ($existing) {
                return redirect()->route('admin.invoice-generator.index', ['draft' => $existing->invoice_id]);
            }

            $customerId = $order->customer_id;
            $pricing    = CustomerPricing::where('customer_id', $customerId)->first();
            $invoice    = $this->createDraftFromOrders($customerId, collect([$order]), collect(), $pricing);
        }

        if (!$invoice) {
            return back()->with('error', 'This order has no invoiceable items.');
        }

        return redirect()->route('admin.invoice-generator.index', ['draft' => $invoice->id]);
    }

    /**
     * Step 2: Admin edits the draft — remove an order/line item,
     * add a flat charge or discount, adjust amounts.
     */
    public function updateDraft(Request $request)
    {
        $request->validate(['invoice_id' => 'required|integer']);

        $invoice = Invoice::draft()->find($request->invoice_id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found or already finalized.',
            ], 404);
        }

        $action = $request->input('action');

        switch ($action) {
            case 'remove_order_ref':
                $request->validate([
                    'invoiceable_type' => 'required|string',
                    'invoiceable_id'   => 'required',
                ]);

                // Removing the pivot row releases the order's reservation.
                InvoiceOrders::where('invoice_id', $invoice->id)
                    ->where('invoiceable_type', $request->invoiceable_type)
                    ->where('invoiceable_id', $request->invoiceable_id)
                    ->delete();

                // Drop line items whose parent order is no longer referenced.
                $remainingParentIds = $this->draftParentOrderIds($invoice);
                InvoiceLineItems::where('invoice_id', $invoice->id)
                    ->whereNotIn('order_id', $remainingParentIds ?: [0])
                    ->delete();
                break;

            case 'add_flat_charge':
                $request->validate([
                    'charge_key' => 'required|string',
                    'amount'     => 'required|numeric',
                    'label'      => 'nullable|string',
                ]);

                InvoiceFlatCharges::create([
                    'invoice_id' => $invoice->id,
                    'charge_key' => $request->charge_key, // e.g. 'custom' or 'discount'
                    'label'      => $request->label,
                    'amount'     => (float) $request->amount, // negative for discount
                ]);
                break;

            case 'remove_flat_charge':
                $request->validate(['flat_charge_id' => 'required|integer']);
                InvoiceFlatCharges::where('invoice_id', $invoice->id)
                    ->where('id', $request->flat_charge_id)
                    ->delete();
                break;

            case 'update_line_item':
                $request->validate([
                    'line_item_id' => 'required|integer',
                    'unit_price'   => 'nullable|numeric',
                    'quantity'     => 'nullable|integer',
                ]);

                $li = InvoiceLineItems::where('invoice_id', $invoice->id)
                    ->where('id', $request->line_item_id)
                    ->first();

                if ($li) {
                    if ($request->filled('unit_price')) {
                        $li->unit_price = (float) $request->unit_price;
                    }
                    if ($request->filled('quantity')) {
                        $li->quantity = (int) $request->quantity;
                    }
                    $li->total_price = $li->unit_price * $li->quantity;
                    $li->save();
                }
                break;

            case 'update_notes':
                $invoice->update(['notes' => $request->input('notes')]);
                break;

            case 'update_invoice_date':
                $request->validate(['invoice_date' => 'required|date']);
                $invoice->update(['invoice_date' => Carbon::parse($request->invoice_date)->format('Y-m-d')]);
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Unknown action.'], 422);
        }

        $this->recomputeTotal($invoice);

        return response()->json(['success' => true, 'draft' => $this->serializeDraft($invoice->fresh())]);
    }

    /**
     * Step 3: Finalize — lock the draft, assign the permanent invoice number,
     * and mark all included orders/recurring_orders as invoiced.
     */
    public function finalize(Request $request)
    {
        $request->validate(['invoice_id' => 'required|integer']);

        $invoice = Invoice::draft()->find($request->invoice_id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found or already finalized.',
            ], 404);
        }

        $refs = InvoiceOrders::where('invoice_id', $invoice->id)->get();

        if ($refs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot finalize an invoice with no orders.',
            ], 422);
        }

        $notes = $request->input('notes', $invoice->notes);

        // Keep the date the admin chose (draft default is the day the draft was created).
        $invoiceDate = $request->filled('invoice_date')
            ? Carbon::parse($request->input('invoice_date'))->format('Y-m-d')
            : ($invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('Y-m-d') : now()->format('Y-m-d'));

        DB::transaction(function () use ($invoice, $refs, $notes, $invoiceDate) {
            $this->recomputeTotal($invoice);

            $invoice->update([
                'status'         => Invoice::STATUS_FINALIZED,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'payment_status' => 'pending',
                'invoice_date'   => $invoiceDate,
                'notes'          => $notes,
            ]);

            foreach ($refs as $ref) {
                if ($ref->invoiceable_type === Order::class) {
                    Order::where('id', $ref->invoiceable_id)->update(['invoice_id' => $invoice->id]);
                } else {
                    RecurringOrder::where('id', $ref->invoiceable_id)->update(['invoice_id' => $invoice->id]);
                }
            }
        });

        return response()->json([
            'success'    => true,
            'invoice_id' => $invoice->id,
            'pdf_url'    => route('admin.invoice-generator.pdf', $invoice->id),
        ]);
    }

    /**
     * Load a saved draft back into the editor.
     */
    public function resumeDraft($invoiceId)
    {
        $invoice = Invoice::draft()->find($invoiceId);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Draft not found.'], 404);
        }

        return response()->json(['success' => true, 'draft' => $this->serializeDraft($invoice)]);
    }

    /**
     * Delete a draft and release its reserved orders (children cascade on delete).
     */
    public function discardDraft(Request $request)
    {
        $request->validate(['invoice_id' => 'required|integer']);

        $invoice = Invoice::draft()->find($request->invoice_id);

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Draft not found or already finalized.'], 404);
        }

        $invoice->delete(); // FK cascade removes invoice_orders / line_items / flat_charges

        return response()->json(['success' => true]);
    }

    /**
     * Generate the consolidated invoice PDF.
     */
    public function downloadPdf($invoiceId)
    {
        $invoice = Invoice::with(['lineItems.product', 'lineItems.order', 'flatCharges'])->findOrFail($invoiceId);

        $invoiceOrders = InvoiceOrders::where('invoice_id', $invoice->id)->get();

        // Pull customer + reference info from the first linked order
        $customer = $invoice->customer;

        $subTotal = $invoice->lineItems->sum('total_price');
        $gstTotal = $invoice->lineItems->sum('gst');
        $pstTotal = $invoice->lineItems->sum('pst');
        $flatChargesTotal = $invoice->flatCharges->sum('amount');

        $pdf = Pdf::loadView('emails.invoice-pdf-consolidated', [
            'invoice'        => $invoice,
            'invoiceOrders'  => $invoiceOrders,
            'customer'       => $customer,
            'lineItems'      => $invoice->lineItems,
            'flatCharges'    => $invoice->flatCharges,
            'subTotal'       => $subTotal,
            'gstTotal'       => $gstTotal,
            'pstTotal'       => $pstTotal,
            'flatChargesTotal' => $flatChargesTotal,
            'totalAmount'    => $invoice->total_amount,
        ])->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('chroot', public_path());

        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }

    /**
     * Email a consolidated invoice (with PDF attached) to its customer.
     * Route to be wired later. Designed to never throw to the caller:
     * any failure is logged and returned as a JSON error so it can't break
     * a surrounding flow.
     */
    public function sendInvoiceEmail($invoiceId)
    {
        try {
            $invoice = Invoice::with(['lineItems.product', 'lineItems.order', 'flatCharges'])->findOrFail($invoiceId);

            // Consolidated invoices carry the customer directly (customer_id).
            $customer = $invoice->customer;

            // Fallback: derive the customer from the first linked order.
            if (!$customer) {
                $firstRef = InvoiceOrders::where('invoice_id', $invoice->id)->first();
                if ($firstRef) {
                    $sourceOrder = $firstRef->invoiceable_type === RecurringOrder::class
                        ? optional(RecurringOrder::find($firstRef->invoiceable_id))->order
                        : Order::find($firstRef->invoiceable_id);
                    $customer = $sourceOrder?->customer;
                }
            }

            $email = $customer->email ?? null;

            if (empty($email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No customer email found for this invoice.',
                ], 422);
            }

            $invoiceOrders    = InvoiceOrders::where('invoice_id', $invoice->id)->get();
            $subTotal         = $invoice->lineItems->sum('total_price');
            $gstTotal         = $invoice->lineItems->sum('gst');
            $pstTotal         = $invoice->lineItems->sum('pst');
            $flatChargesTotal = $invoice->flatCharges->sum('amount');

            // Render the same PDF used for download, pass raw bytes to the Mailable.
            $pdfData = Pdf::loadView('emails.invoice-pdf-consolidated', [
                'invoice'          => $invoice,
                'invoiceOrders'    => $invoiceOrders,
                'customer'         => $customer,
                'lineItems'        => $invoice->lineItems,
                'flatCharges'      => $invoice->flatCharges,
                'subTotal'         => $subTotal,
                'gstTotal'         => $gstTotal,
                'pstTotal'         => $pstTotal,
                'flatChargesTotal' => $flatChargesTotal,
                'totalAmount'      => $invoice->total_amount,
            ])->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', false)
                ->setOption('chroot', public_path())
                ->output();

            Mail::to($email)->send(new ConsolidatedInvoiceMail($invoice, $customer, $pdfData));

            $this->logInvoiceEmail($invoice->id, $email, InvoiceEmailLog::STATUS_SENT);

            return response()->json([
                'success' => true,
                'message' => 'Invoice emailed to ' . $email,
            ]);

        } catch (\Throwable $e) {
            \Log::error('Send consolidated invoice email failed: ' . $e->getMessage());

            $this->logInvoiceEmail($invoiceId, $email ?? null, InvoiceEmailLog::STATUS_FAILED, $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice email. Please try again.',
            ], 500);
        }
    }

    /**
     * Read-only: return this invoice's email send history as JSON (date,
     * recipient, status, who sent it). Read-only, so it cannot affect any
     * existing flow.
     */
    public function emailHistory($invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $logs = $invoice->emailLogs()->with('sender')->get()->map(fn ($log) => [
            'sent_to'    => $log->sent_to,
            'status'     => $log->status,
            'error'      => $log->error_message,
            'sent_by'    => $log->sender->name ?? null,
            'created_at' => $log->created_at->format('Y-m-d H:i'),
        ]);

        return response()->json([
            'success' => true,
            'logs'    => $logs,
        ]);
    }

    /**
     * Record an invoice email send attempt. Deliberately never throws -- a
     * logging failure must never affect the send response the caller sees.
     */
    private function logInvoiceEmail($invoiceId, ?string $sentTo, string $status, ?string $errorMessage = null): void
    {
        try {
            InvoiceEmailLog::create([
                'invoice_id'    => $invoiceId,
                'sent_to'       => $sentTo ?? 'unknown',
                'sent_by'       => auth()->id(),
                'status'        => $status,
                'error_message' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to record invoice email log: ' . $e->getMessage());
        }
    }

    // ===================== Helpers =====================

    private function buildLineItem($item, $pricing, $orderId, $deliveryDate = null): array
    {
        if ($pricing) {
            // One per-lb ice price per customer, for both pellets (1) and blocks (2).
            if (in_array($item->product_id, [1, 2]) && $pricing->ice_price !== null) {
                $unitPrice = $pricing->ice_price;
            } else {
                $unitPrice = $item->unit_price;
            }
        } else {
            // No customer pricing — fall back to the product's default price
            $unitPrice = $item->product?->price ?? $item->unit_price;
        }

        return [
            'order_id'      => $orderId,
            'product_id'    => $item->product_id,
            'description'   => $item->product->product_name ?? 'Product',
            'quantity'      => $item->amount_of_items,
            'unit_price'    => (float) $unitPrice,
            'total_price'   => (float) $unitPrice * $item->amount_of_items,
            'delivery_date' => $deliveryDate,
        ];
    }

    /**
     * Hazmat/delivery/pickup fees as per-order dated line items (product_id
     * null — not a box product, so PST never applies to these). Hazmat has
     * no per-order flag today, so it is charged on every order in the
     * invoice when the customer has a non-zero hazmat_fee, matching the
     * previous once-per-invoice behaviour but now itemised per delivery.
     *
     * Delivery depends on the customer's delivery_mode (novex when they have
     * no pricing row): fixed charges their delivery_fee, none charges nothing,
     * novex quotes it. Pickup orders never get a delivery row.
     */
    private function buildFeeLineItems($pricing, $customer, $order, $deliveryDate): array
    {
        $fees = [];
        $orderId = $order->id;

        if ($pricing && !empty($pricing->hazmat_fee) && (float) $pricing->hazmat_fee != 0) {
            $fees[] = $this->feeLineItem('Hazmat Fee', (float) $pricing->hazmat_fee, $orderId, $deliveryDate);
        }

        if ($order->pickup_delivery === 'delivery') {
            $mode = $pricing->delivery_mode ?? CustomerPricing::DELIVERY_NOVEX;

            if ($mode === CustomerPricing::DELIVERY_FIXED) {
                if ((float) $pricing->delivery_fee != 0) {
                    $fees[] = $this->feeLineItem('Delivery', (float) $pricing->delivery_fee, $orderId, $deliveryDate);
                }
            } elseif ($mode === CustomerPricing::DELIVERY_NOVEX) {
                // A $0 Delivery row is added when Novex can't quote, so the
                // price can be typed into the draft (the UI highlights it).
                $quote = $this->novexDeliveryQuote($customer, $order);
                $fees[] = $this->feeLineItem('Delivery', $quote ?? 0.0, $orderId, $deliveryDate);
            }
        }

        if ($order->pickup_delivery === 'pickup' && $pricing && !empty($pricing->pickup_fee) && (float) $pricing->pickup_fee != 0) {
            $fees[] = $this->feeLineItem('Pickup Fee', (float) $pricing->pickup_fee, $orderId, $deliveryDate);
        }

        return $fees;
    }

    /**
     * Novex delivery quote for an order: from the customer's pickup location
     * (supply_location_id) to the customer's address, weighed by the dry ice
     * on the order. Returns null when it can't be quoted.
     */
    private function novexDeliveryQuote($customer, $order): ?float
    {
        $pickup = $customer?->supplyLocation;

        if (!$pickup || !$customer->address || !$customer->city || !$customer->province || !$customer->postal_code) {
            return null;
        }

        $weight = $order->items->filter(fn ($item) => ($item->product->unit ?? null) !== 'box')
            ->sum('amount_of_items');

        $result = app(NovexQuoteService::class)->quote($pickup, [
            'name'        => $customer->name,
            'street'      => $customer->address,
            'unit'        => '',
            'city'        => $customer->city,
            'province'    => $customer->province,
            'postal_code' => $customer->postal_code,
            'contact'     => $customer->name ?: 'N/A',
            'phone'       => $customer->phone ?: 'N/A',
            'email'       => $customer->email ?: 'N/A',
        ], (float) $weight);

        return $result['amount'] ?? null;
    }

    private function feeLineItem(string $label, float $amount, $orderId, $deliveryDate): array
    {
        return [
            'order_id'      => $orderId,
            'product_id'    => null,
            'description'   => $label,
            'quantity'      => 1,
            'unit_price'    => $amount,
            'total_price'   => $amount,
            'delivery_date' => $deliveryDate,
        ];
    }

    /**
     * Distinct parent-order ids still referenced by a draft's pivot rows
     * (used to decide which line items to drop when an order is removed).
     */
    private function draftParentOrderIds(Invoice $invoice): array
    {
        $ids = [];
        foreach (InvoiceOrders::where('invoice_id', $invoice->id)->get() as $ref) {
            if ($ref->invoiceable_type === Order::class) {
                $ids[] = $ref->invoiceable_id;
            } else {
                $recurring = RecurringOrder::find($ref->invoiceable_id);
                if ($recurring) {
                    $ids[] = $recurring->order_id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    private function generateInvoiceNumber(): string
    {
        return Invoice::generateInvoiceNumber();
    }

    /**
     * Keep invoices.total_amount in sync with line items (+ their live
     * GST/PST) and flat charges.
     */
    private function recomputeTotal(Invoice $invoice): void
    {
        $lineItems = InvoiceLineItems::with('product')->where('invoice_id', $invoice->id)->get();
        $sub  = $lineItems->sum('total_price');
        $gst  = $lineItems->sum('gst');
        $pst  = $lineItems->sum('pst');
        $flat = InvoiceFlatCharges::where('invoice_id', $invoice->id)->sum('amount');
        $invoice->update(['total_amount' => round($sub + $gst + $pst + $flat, 2)]);
    }

    /**
     * Turn a draft Invoice (+ children) into the JSON shape the editor expects.
     */
    private function serializeDraft(Invoice $invoice): array
    {
        $invoice->loadMissing(['lineItems.product', 'flatCharges']);

        $orderRefs = [];
        $dates = [];

        foreach (InvoiceOrders::where('invoice_id', $invoice->id)->get() as $ref) {
            if ($ref->invoiceable_type === RecurringOrder::class) {
                $rec    = RecurringOrder::with('order')->find($ref->invoiceable_id);
                $parent = $rec?->order;
                $date   = $rec?->scheduled_delivery_date;
                $label  = 'Recurring #' . $ref->invoiceable_id
                    . ' (Order #' . str_pad($parent->id ?? 0, 4, '0', STR_PAD_LEFT) . ')';
            } else {
                $order = Order::find($ref->invoiceable_id);
                $date  = $order?->delivery_date;
                $label = 'Order #' . $ref->invoiceable_id;
            }

            if ($date) {
                $dates[] = Carbon::parse($date);
            }

            $orderRefs[] = [
                'invoiceable_type' => $ref->invoiceable_type,
                'invoiceable_id'   => $ref->invoiceable_id,
                'order_type'       => $ref->order_type,
                'label'            => $label,
                'delivery_date'    => $date ? Carbon::parse($date)->format('Y-m-d H:i') : '',
            ];
        }

        // Chronological; within a date the Delivery row comes first, then creation order.
        $orderRefs = collect($orderRefs)->sortBy('delivery_date')->values()->all();

        $lineItems = $invoice->lineItems
            ->sortBy(fn ($li) => sprintf(
                '%s-%d-%08d',
                $li->delivery_date ? Carbon::parse($li->delivery_date)->format('Ymd') : '00000000',
                strcasecmp(trim($li->description), 'Delivery') === 0 ? 0 : 1,
                $li->id
            ))
            ->map(fn ($li) => [
            'id'            => $li->id,
            'order_id'      => $li->order_id,
            'product_id'    => $li->product_id,
            'description'   => $li->description,
            'quantity'      => $li->quantity,
            'unit_price'    => (float) $li->unit_price,
            'total_price'   => (float) $li->total_price,
            'delivery_date' => $li->delivery_date ? Carbon::parse($li->delivery_date)->format('Y-m-d') : null,
            'gst'           => (float) $li->gst,
            'pst'           => (float) $li->pst,
        ])->values()->all();

        $flatCharges = $invoice->flatCharges->map(fn ($fc) => [
            'id'         => $fc->id,
            'charge_key' => $fc->charge_key,
            'label'      => $fc->label,
            'amount'     => (float) $fc->amount,
        ])->values()->all();

        $subTotal  = $invoice->lineItems->sum('total_price');
        $gstTotal  = $invoice->lineItems->sum('gst');
        $pstTotal  = $invoice->lineItems->sum('pst');
        $flatTotal = $invoice->flatCharges->sum('amount');

        return [
            'id'           => $invoice->id,
            'customer_id'  => $invoice->customer_id,
            'notes'        => $invoice->notes,
            'invoice_date' => $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '',
            'start_date'   => $dates ? collect($dates)->min()->format('Y-m-d') : '',
            'end_date'     => $dates ? collect($dates)->max()->format('Y-m-d') : '',
            'order_refs'   => $orderRefs,
            'line_items'   => $lineItems,
            'flat_charges' => $flatCharges,
            'totals'       => [
                'sub_total'  => round($subTotal, 2),
                'gst_total'  => round($gstTotal, 2),
                'pst_total'  => round($pstTotal, 2),
                'flat_total' => round($flatTotal, 2),
                'total'      => round($subTotal + $gstTotal + $pstTotal + $flatTotal, 2),
            ],
        ];
    }


    public function viewInvoice($invoiceId)
    {
        if (!request()->ajax()) {
            return redirect()->back()->with('error', 'Direct access not allowed.');
        }


        $invoice = Invoice::with(['lineItems.product', 'lineItems.order', 'flatCharges'])->findOrFail($invoiceId);

        $invoiceOrders = InvoiceOrders::where('invoice_id', $invoice->id)->get();

        // Pull customer + reference info from the first linked order
        $customer = $invoice->customer;

        $subTotal = $invoice->lineItems->sum('total_price');
        $gstTotal = $invoice->lineItems->sum('gst');
        $pstTotal = $invoice->lineItems->sum('pst');
        $flatChargesTotal = $invoice->flatCharges->sum('amount');

        if (request()->ajax()) {
            return view('emails.invoice-pdf-consolidated', [
                'invoice'        => $invoice,
                'invoiceOrders'  => $invoiceOrders,
                'customer'       => $customer,
                'lineItems'      => $invoice->lineItems,
                'flatCharges'    => $invoice->flatCharges,
                'subTotal'       => $subTotal,
                'gstTotal'       => $gstTotal,
                'pstTotal'       => $pstTotal,
                'flatChargesTotal' => $flatChargesTotal,
                'totalAmount'    => $invoice->total_amount,
            ])->render();
        }

        abort(403);
    }

}
