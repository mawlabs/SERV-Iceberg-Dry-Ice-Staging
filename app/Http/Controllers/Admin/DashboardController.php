<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RecurringOrder;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private const UPCOMING_DAYS = 10; // dashboard lists show every delivery in the next N days
    private const PAST_ROWS = 5;     // ...plus this many most-recent past ones (one-time table)
    private const RECURRING_ROWS = 15; // recurring table: the next N upcoming deliveries

    public function index() {

        $orders = Order::select('id', 'customer_name', 'delivery_date', 'recurring', 'status', 'total_cost', 'address', 'origin', 'amount_of_boxes', 'amount_of_ice', 'invoice_id')
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'invoice_id' => $order->invoice_id,
                    'customer_name' => $order->customer_name,
                    'delivery_date' => $order->delivery_date,
                    'status' => $order->status,
                    'amount_of_ice' => $order->amount_of_ice,
                    'amount_of_boxes' => $order->amount_of_boxes,
                    'total_cost' => $order->total_cost,
                    'origin' => $order->origin,
                    'address' => $order->address,
                    'type' => 'order'
                ];
            });

        $recurringOrdersData = RecurringOrder::with('order')
            ->get()
            ->map(function($recurring) {
                return [
                    'id' => $recurring->order->id,
                    'invoice_id' => $recurring->invoice_id,
                    'customer_name' => $recurring->order->customer_name,
                    'delivery_date' => $recurring->scheduled_delivery_date,
                    'status' => $recurring->status,
                    'amount_of_ice' => $recurring->order->amount_of_ice,
                    'amount_of_boxes' => $recurring->order->amount_of_boxes,
                    'total_cost' => $recurring->order->total_cost,
                    'origin' => $recurring->order->origin,
                    'address' => $recurring->order->address,
                    'type' => 'recurring'
                ];
            });

        $manualOrders = $orders->concat($recurringOrdersData)
            ->where('origin', 'manual')
            ->sortByDesc('delivery_date')
            ->take(15)
            ->values();

        $onlineOrders = $orders->concat($recurringOrdersData)
            ->where('origin', 'online')
            ->sortByDesc('id')
            ->take(15)
            ->values();

        // REMOVED the date filter here too for consistency
        $recurringOrders = RecurringOrder::where('status', RecurringOrder::OPEN)
            ->whereHas('order', function ($query) {
                $query->where('recurring', 'recurring')
                    ->whereIn('status', [Order::VALID, Order::COMPLETED]);
            })
            ->with(['order'])
            ->orderBy('invoice_id', 'desc')
            ->take(15)
            ->get();

        // Every delivery from today through the next UPCOMING_DAYS days (soonest first - never cut off by a
        // row cap), followed by the most recent PAST_ROWS past ones (newest first). Order ids are not a
        // delivery sequence (imported orders get ids in import order), so id is only a tiebreak.
        $today = Carbon::today()->toDateString();
        $windowEnd = Carbon::today()->addDays(self::UPCOMING_DAYS)->toDateString();

        $oneTimeBase = fn () => Order::where('recurring', 'non-recurring')
            ->whereIn('status', [Order::VALID, Order::COMPLETED]);
        $oneTimeOrders = $oneTimeBase()
            ->whereDate('delivery_date', '>=', $today)->whereDate('delivery_date', '<=', $windowEnd)
            ->orderBy('delivery_date')->orderBy('id', 'desc')
            ->get()
            ->concat(
                $oneTimeBase()
                    ->whereDate('delivery_date', '<', $today)
                    ->orderBy('delivery_date', 'desc')->orderBy('id', 'desc')
                    ->take(self::PAST_ROWS)
                    ->get()
            );

        // Get recurring orders from Orders table
        // Imported (legacy_id) parents are schedule templates, dated just before cutover and locked as paid;
        // their real deliveries are the recurring_orders rows, so they must not show up as deliveries.
        $recurringFromOrders = Order::where('recurring', 'recurring')
            ->whereNull('legacy_id')
            ->whereIn('status', [Order::VALID, Order::COMPLETED])
            ->get();

        // Get recurring orders from RecurringOrder table - REMOVED DATE FILTER
        $recurringFromRecurringTable = RecurringOrder::where('status', RecurringOrder::OPEN)
            ->whereHas('order', function ($query) {
                $query->where('recurring', 'recurring')
                    ->whereIn('status', [Order::VALID, Order::COMPLETED]); // Changed to include COMPLETED
            })
            ->with(['order'])
            ->get();

        // Recurring table: the next RECURRING_ROWS upcoming deliveries, soonest first. Instances use their own
        // scheduled date; parent rows use their template delivery_date, so a parent only appears while that
        // date is still ahead (imported/older parents are past-dated templates and never show).
        $allRecurringOrders = collect()
            ->merge($recurringFromOrders)
            ->merge($recurringFromRecurringTable)
            ->map(fn ($item) => [$this->deliveryDateOf($item), $item])
            ->filter(fn ($pair) => $pair[0] >= $today)
            ->sort(fn ($a, $b) => [$a[0], $a[1]->id] <=> [$b[0], $b[1]->id])
            ->take(self::RECURRING_ROWS)
            ->map(fn ($pair) => $pair[1])
            ->values();

        return view('vendor.backpack.base.dashboard', compact(
            'manualOrders',
            'onlineOrders',
            'recurringOrders',
            'oneTimeOrders',
            'allRecurringOrders',
        ));
    }

    private function deliveryDateOf($item): string
    {
        $raw = $item instanceof RecurringOrder ? $item->scheduled_delivery_date : $item->delivery_date;
        return $raw ? Carbon::parse($raw)->toDateString() : '0000-00-00';
    }
}
