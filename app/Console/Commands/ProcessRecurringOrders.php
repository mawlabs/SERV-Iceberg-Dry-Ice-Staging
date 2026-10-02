<?php

namespace App\Console\Commands;

use App\Mail\OrderPlacedMail;
use App\Models\Invoice;
use App\Models\SupplyLocation;
use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\RecurringOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Mail;


class ProcessRecurringOrders extends Command
{
    protected $signature = 'orders:process-recurring';
    protected $description = 'Process recurring orders and create instances';

    public function handle()
    {
        $today = Carbon::now();
        $todayWeekday = $today->dayOfWeek;

        $recurringOrders = Order::where('recurring', Order::RECURRING)->where('status',Order::VALID)->get();
//        Log::info($todayWeekday);
//        Log::info(count($recurringOrders));
        foreach ($recurringOrders as $order) {
            $deliveryDate = Carbon::parse($order->delivery_date);
            $orderWeekday = $deliveryDate->dayOfWeek;
//            Log::info('------------------------');
//            Log::info($orderWeekday);
            if ($orderWeekday === $todayWeekday) {
                $nextDeliveryDate = $today->copy()->addWeek();

                $existingRecurring = RecurringOrder::where('order_id', $order->id)
                    ->where('scheduled_delivery_date', $nextDeliveryDate->toDateString())
                    ->exists();

                if (!$existingRecurring) {
                    DB::beginTransaction();
                    try {
                        $recurringOrder = RecurringOrder::create([
                            'order_id' => $order->id,
                            'scheduled_delivery_date' => $nextDeliveryDate,
                            'status' => 'open',
                        ]);

//                        $invoiceService = new InvoiceService();
//                        $invoice = $invoiceService->createInvoiceForRecurringOrder($recurringOrder);
//
//                        // Attach invoice_id to recurring order
//                        $recurringOrder->update([
//                            'invoice_id' => $invoice->invoice_number,
//                        ]);

                        DB::commit();

//                        $order = RecurringOrder::with(['invoice', 'order', 'items'])
//                            ->where('id', $recurringOrder->id)
//                            ->first();
//                        Mail::to($order->order->email)->send(new OrderPlacedMail($order, 'recurring'));
                        Log::info("Created recurring order for Order ID: {$order->id}");
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error("Failed to create recurring order for Order ID: {$order->id}. Error: " . $e->getMessage());
                        throw $e; // or handle gracefully
                    }
                }
            }
        }

        // Process recurring orders due in 2 days
        $twoDaysFromNow = $today->copy()->addDays(2)->toDateString();

        $recurringOrdersDue = RecurringOrder::where('scheduled_delivery_date', $twoDaysFromNow)
            ->where('status', 'open')
            ->whereNull('novex_order_id')
            ->where('push', false) // push=1 with no courier id = already booked by the legacy site (legacy:import)
            ->get();

        foreach ($recurringOrdersDue as $recurringOrder) {
            $result = $this->pushToNovex($recurringOrder);
            if ($result) {
                Log::info("Pushed recurring order to Novex for Order ID: {$recurringOrder->order_id}");
            } else {
                Log::error("Failed to push recurring order to Novex for Order ID: {$recurringOrder->order_id}");
            }
        }

        Log::info('Recurring orders processing completed.');
    }

    function pushToNovex(RecurringOrder $recurringOrder)
    {
        try {
            $order = $recurringOrder->order;

            // Resolve supplier: explicit supplier_id first, falling back to the
            // customer's assigned supply location for manual/account-holder orders
            // (manual orders never set supplier_id directly).
            $supplier = null;
            if ($order->supplier_id) {
                $supplier = SupplyLocation::find($order->supplier_id);
            } elseif ($order->origin === 'manual') {
                $supplier = SupplyLocation::find($order->customer?->supply_location_id);
            }

            if (!$supplier) {
                Log::error('Supplier not found for recurring order', [
                    'order_id' => $order->id,
                    'supplier_id' => $order->supplier_id,
                ]);
                return false;
            }

            // Build delivery payload — weight from the pellet line item (matches the
            // manual push logic in SupplierController), not the non-existent orders.amount.
            $pelletItem = $order->items->first(function ($item) {
                return $item->product && str_contains(strtolower($item->product->product_name), 'pellets');
            });
            $weight = $pelletItem?->amount_of_items ?: 1;
            $dimensions = max(12, ceil($weight / 2));

            $payload = [
                'callerName' => 'Tyler',
                'reference' => '',
                'pickup' => [
                    'name' => $supplier->name,
                    'street' => $supplier->address,
                    'unit' => $supplier->unit ?? '',
                    'city' => $supplier->city,
                    'province' => $supplier->province,
                    'postalCode' => $supplier->postal,
                    "phone" => $supplier->phone ?? "604 255-6007",
                    'country' => 'CAN',
                    'instructions' => 'Call 604-255-6007 if given phone is not responding',
                ],
                'delivery' => [
                    'name' => $order->customer?->name ?? $order->location_name ?? $order->customer_name ?? 'Customer',
                    'street' => $order->address,
                    'unit' => $order->unit ?? '',
                    'city' => $order->city,
                    'province' => $order->province,
                    'postalCode' => $order->postal_code,
                    'country' => 'CAN',
                    'instructions' => 'Call 604-255-6007 if given phone is not responding',
                    'contact' => $order->contact_name ?? 'Customer',
                    'phone' => $order->phone ?? '604 524-0609',
                    'notificationEmail' => $order->email,
                ],
                'serviceTypeId' => 4,
                'vehicleTypeId' => 1,
                'readyBy'=> $recurringOrder->scheduled_delivery_date,
                'instructions'=> $order->notes ?? "",
                'packages' => [[
                    'typeId' => 3,
                    'length' => $dimensions,
                    'width' => 1,
                    'height' => 1,
                    'weight' => $weight,
                    'count' => 1
                ]]
            ];

//            Log::info('Pushing recurring order to Novex', ['recurring_order_id' => $recurringOrder->id, 'payload' => $payload]);

            $response = Http::withOptions([
                'verify' => config('services.http_verify'),
            ])->withHeaders([
                'Authorization' => 'Basic ' . config('services.novex.auth_key'),
                'Content-Type' => 'application/json'
            ])->post(config('services.novex.push_url'), $payload);

            if ($response->successful()) {
                $body = json_decode($response->getBody());
                $recurringOrder->novex_order_id = $body->orderNumber ?? null;
                $recurringOrder->push = true;
                $recurringOrder->save();

                return true;
            } else {
                Log::error('Failed to push recurring order to Novex', [
                    'recurring_order_id' => $recurringOrder->id,
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Novex Push Exception for recurring order: ' . $e->getMessage(), [
                'recurring_order_id' => $recurringOrder->id,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
