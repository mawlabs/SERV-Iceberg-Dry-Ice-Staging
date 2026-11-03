<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Mail\OrderPlacedMail;
use App\Models\Invoice;
use App\Models\InvoiceOrders;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerDashboardController extends Controller
{
    public function index()
    {
        // You can pass data to the view if needed

        $customerId = auth()->guard('customer')->id();

        // Get the order which has the next recurring due
        $orderWithNextRecurringDue = Order::where('recurring', Order::RECURRING)
            ->where('status',Order::VALID)
            ->where('customer_id', $customerId)
            ->whereHas('recurringOrders', function ($query) {
                $query->where('status', 'open')
                    ->where('scheduled_delivery_date', '>', now());
            })
            ->with(['recurringOrders' => function ($query) {
                $query->where('status', 'open')
                    ->where('scheduled_delivery_date', '>', now())
                    ->orderBy('scheduled_delivery_date');
            }])
            ->get()
            ->sortBy(function ($order) {
                return $order->recurringOrders->first()?->scheduled_delivery_date;
            })
            ->first();

        // Get orders for the current customer with pagination
        $orders = Order::with('invoice')
            ->where('customer_id', $customerId)
            ->get();

        // Get all recurring orders for this customer
        $recurringOrders = \App\Models\RecurringOrder::with('invoice')
            ->whereHas('order', function($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->get();

        // Combine both collections
        $allOrders = $orders->concat($recurringOrders);

        $allOrders = $allOrders->map(function($order) {
            if ($order instanceof \App\Models\RecurringOrder) {
                // Add parent order's total_cost to recurring order
                $order->total_cost = $order->order->total_cost ?? 0;
            }
            return $order;
        });

        // Sort by invoice_id descending
        $allOrders = $allOrders->sortByDesc('created_at')->values();

        // Manual pagination
        $perPage = request('per_page', 10);
        $currentPage = request('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $paginatedOrders = new \Illuminate\Pagination\LengthAwarePaginator(
            $allOrders->slice($offset, $perPage)->values(),
            $allOrders->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $orders = $paginatedOrders;


        // Check if pagination is being used
        $isPaginated = $orders instanceof \Illuminate\Pagination\LengthAwarePaginator;

        return view('website.customer.customer_dashboard',compact('orders', 'isPaginated','orderWithNextRecurringDue'));
    }

    public function myOrders(Request $request)
    {
        $customerId = auth()->guard('customer')->id();
        $filter = $request->query('filter', '');

        if ($filter === 'regular') {
            // Only regular orders
            $allOrders = Order::with('invoice')
                ->where('customer_id', $customerId)
                ->where('recurring', '!=', Order::RECURRING)
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($filter === 'recurring') {
            // Only recurring order instances
            $allOrders = \App\Models\RecurringOrder::with('invoice')
                ->whereHas('order', function($query) use ($customerId) {
                    $query->where('customer_id', $customerId);
                })
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // All orders
            $regularOrders = Order::with('invoice')
                ->where('customer_id', $customerId)
                ->get();

            $recurringOrders = \App\Models\RecurringOrder::with('invoice')
                ->whereHas('order', function($query) use ($customerId) {
                    $query->where('customer_id', $customerId);
                })
                ->get();


            $allOrders = $regularOrders->concat($recurringOrders)
                ->sortByDesc('created_at')
                ->values();


        }

        // Pagination...
        $perPage = request('per_page', 10);
        $currentPage = request('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $paginatedOrders = new \Illuminate\Pagination\LengthAwarePaginator(
            $allOrders->slice($offset, $perPage)->values(),
            $allOrders->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $orders = $paginatedOrders;

        return view('website.customer.my_orders', compact('orders'));
    }

    public function orderDetails($id, Request $request)
    {
        $recurring = intval($request->query('recurring'));
        $recurringId = intval($request->query('recurring_id', 0));

        if($recurring && $recurringId){
            // Load specific recurring order instance
            $order = Order::where('recurring', Order::RECURRING)
                ->where('id', $id)
                ->with(['recurringOrders' => function ($query) use ($recurringId) {
                    $query->where('id', $recurringId);
                }])
                ->with(['items.product'])
                ->firstOrFail();

            $status = null;
            if ($order->recurringOrders?->first()->novex_order_id) {
                $cacheKey = 'order_status_' . $order->recurringOrders->first()->novex_order_id;
                $status = Cache::remember($cacheKey, now()->addHours(3), function () use ($order) {
                    return $this->getOrderStatus($order->recurringOrders->first()->novex_order_id);
                });
            }
        } else {
            // Regular order
            $order = Order::with(['items.product'])->findOrFail($id);
            $status = null;

            if ($order->novex_order_id) {
                $cacheKey = 'order_status_' . $order->novex_order_id;
                $status = Cache::remember($cacheKey, now()->addHours(3), function () use ($order) {
                    return $this->getOrderStatus($order->novex_order_id);
                });
            }
        }

        if ($order->customer_id !== auth()->guard('customer')->id()) {
            abort(403);
        }

        return view('website.customer.partials.order_details', compact('order', 'status', 'recurring'));
    }

    public function orderInvoice($id, Request $request)
    {
        $isRecurring = intval($request->query('is_recurring', 0));

        if ($isRecurring) {
            // Load recurring order with parent order relationship
            $recurringOrder = \App\Models\RecurringOrder::with(['order.items.product', 'invoice'])
                ->findOrFail($id);

            if ($recurringOrder->order->customer_id !== auth()->guard('customer')->id()) {
                abort(403);
            }

            // Pass as $order to reuse same template
            $order = $recurringOrder->order;
            $order->invoice_display = $recurringOrder->invoice;
            $order->is_recurring_instance = true;
            $order->recurring_delivery_date = $recurringOrder->scheduled_delivery_date;
            $order->recurring_invoice_number = $recurringOrder->invoice->invoice_number ?? 'N/A';
            $order->recurring_created_at = $recurringOrder->created_at;
            $order->recurring_payment_status = optional($recurringOrder->invoice)->payment_status;

            return response()->json([
                'html' => view('website.customer.partials.invoice', compact('order'))->render()
            ]);
        } else {
            $order = Order::with(['items.product', 'invoice'])->findOrFail($id);

            if ($order->customer_id !== auth()->guard('customer')->id()) {
                abort(403);
            }

            $order->invoice_display = $order->invoice;
            $order->is_recurring_instance = false;

            return response()->json([
                'html' => view('website.customer.partials.invoice', compact('order'))->render()
            ]);
        }
    }

    /**
     * Fetch a finalized CONSOLIDATED invoice (rendered) for the modal, by invoice id.
     * Used by account-holder orders whose invoice is the new consolidated shape
     * (invoiceable is null, so the legacy per-order view can't render it).
     * Scoped to the logged-in customer and to finalized invoices only (never drafts).
     */
    public function consolidatedInvoice($invoiceId)
    {
        $customerId = auth()->guard('customer')->id();

        $invoice = Invoice::where('status', Invoice::STATUS_FINALIZED)
            ->where('customer_id', $customerId)
            ->with(['lineItems.product', 'lineItems.order', 'flatCharges', 'customer'])
            ->findOrFail($invoiceId);

        $invoiceOrders = InvoiceOrders::where('invoice_id', $invoice->id)->get();

        return response()->json([
            'html' => view('emails.invoice-pdf-consolidated', [
                'invoice'          => $invoice,
                'invoiceOrders'    => $invoiceOrders,
                'customer'         => $invoice->customer,
                'lineItems'        => $invoice->lineItems,
                'flatCharges'      => $invoice->flatCharges,
                'subTotal'         => $invoice->lineItems->sum('total_price'),
                'gstTotal'         => $invoice->gstTotal(),
                'pstTotal'         => $invoice->pstTotal(),
                'flatChargesTotal' => $invoice->flatCharges->sum('amount'),
                'totalAmount'      => $invoice->total_amount,
            ])->render(),
        ]);
    }

    function getOrderStatus($orderNumber)
    {
        try {
            $response = Http::withOptions([
                'verify' => config('services.http_verify'),
            ])->withHeaders([
                'Authorization' => 'Basic ' . config('services.novex.auth_key'),
                'Content-Type' => 'application/json'
            ])->get(config('services.novex.push_url') . "/{$orderNumber}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['status'];
            }

            return null;

        } catch (\Exception $e) {
            Log::info($e->getMessage());
            return null;
        }
    }



}
