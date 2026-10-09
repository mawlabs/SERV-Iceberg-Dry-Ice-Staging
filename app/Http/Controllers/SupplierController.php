<?php

namespace App\Http\Controllers;
use App\Exceptions\SupplierLookupException;
use App\Models\Order;
use App\Services\ClosestSupplierService;
use App\Services\NovexQuoteService;
use App\Models\SupplyLocation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function checkClosestSupplier(Request $request, ClosestSupplierService $service)
    {
        // Validate required parameters
        $street = $request->get('street');
        $city = $request->get('city');
        $province = $request->get('province');

        if (!$street || !$city || !$province) {
            return response()->json([
                'success' => false,
                'error' => 'Street, city, and province are required parameters'
            ], 400);
        }

        try {
            $closest = $service->findClosest(
                $street,
                $city,
                $province,
                true // set false to skip Google API
            );
        } catch (SupplierLookupException $e) {
            // The lookup itself failed (not "no supplier here"): say so, so the page
            // doesn't tell the customer we don't deliver to their address.
            return response()->json([
                'success' => false,
                'error' => 'We could not check delivery coverage right now. Please try again in a moment.'
            ], 503);
        }

        if (!$closest) {
            return response()->json(['message' => 'No supplier found'], 200);
        }

        return response()->json([
            'closest_supplier' => $closest,
            'distance_meters' => $closest->distance ?? 'unknown',
        ]);
    }

    public function getDeliveryQuote(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'supplier_id' => 'required|integer',
                'delivery.name' => 'required|string',
                'delivery.street' => 'required|string',
                'delivery.unit' => 'nullable|string', // Added this line
                'delivery.city' => 'required|string',
                'delivery.province' => 'required|string',
                'delivery.postal_code' => 'required|string',
                'delivery.contact' => 'required|string',
                'delivery.phone' => 'required|string',
                'delivery.email' => 'required|email',
                'weight' => 'required|numeric|min:1',
                'ready_by' => 'nullable|string'
            ]);

            // Get supplier location from database
            $supplierLocation = SupplyLocation::find($validated['supplier_id']);

            if (!$supplierLocation) {
                return response()->json([
                    'success' => false,
                    'error' => 'Supplier location not found'
                ], 404);
            }

            // Get delivery details
            $delivery = $validated['delivery'];
            $weight = $validated['weight'];

            // Same Novex request (and 25% markup) the invoice generator uses. It returns
            // ['error' => ...] for anything that is not a real price, including Novex errors,
            // timeouts and a zero total.
            $quoteAmount = app(NovexQuoteService::class)->quote($supplierLocation, $delivery, (float) $weight);

            if (!isset($quoteAmount['amount'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unable to calculate delivery quote at this time',
                ], 200);
            }

            return response()->json([
                'success' => true,
                'quote' => [
                    'total' => round($quoteAmount['amount'], 2)
                ],
                'total' => round($quoteAmount['amount'], 2),
                'supplier' => [
                    'id' => $supplierLocation->id,
                    'name' => $supplierLocation->name,
                    'city' => $supplierLocation->city
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log validation errors for debugging
            Log::error('Validation error in getDeliveryQuote', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Delivery quote error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while calculating the delivery quote'
            ], 500);
        }
    }

    public function pushToNovex($id, ClosestSupplierService $service)
    {
        try {
            $order = Order::with(['items.product','customer'])->findOrFail($id);
            $pelletItem = $order->items->first(function ($item) {
                return str_contains(strtolower($item->product->product_name), 'pellets');
            });

            if ($order->push == 1 && $order->novex_order_id) {
                return response()->json(['success' => false, 'error' => 'Order already pushed']);
            }
            $supplier = null;
           if($order->origin == 'manual'){
               $supplier = SupplyLocation::whereId($order->customer?->supply_location_id)->first();
           }else{
               // Use ClosestSupplierService
               $supplier = $service->findClosest(
                   $order->address,
                   $order->city,
                   $order->province,
                   false // set to false if you want to skip Google API on backend
               );
           }
            if (!$supplier || !$supplier->id) {
                return response()->json(['success' => false, 'error' => 'Closest supplier not found']);
            }
            // Build delivery payload
            $weight = $order->amount_of_ice ? ($pelletItem?->amount_of_items ?? 1) : 1;
            $dimensions = max(12, ceil($weight / 2));
            // Determine delivery details: use customer fields for manual orders, fallback to order fields
            $customer = $order->customer;

            if ($order->origin == 'manual' && $customer) {
                $deliveryName    = $customer->name ?? $order->location_name ?? $order->customer_name ?? 'Customer';
                $deliveryStreet  = $customer->address ?? $order->address;
                $deliveryUnit    = $order->unit ?? '';
                $deliveryCity    = $customer->city ?? $order->city;
                $deliveryProvince = $customer->province ?? $order->province;
                $deliveryPostal  = $customer->postal_code ?? $order->postal_code;
                $deliveryContact = $customer->name ?? $order->contact_name ?? 'Customer';
                $deliveryPhone   = $customer->phone ?? $order->phone ?? '604 524-0609';
                $deliveryEmail   = $customer->email ?? $order->email;
            } else {
                $deliveryName    = $order->location_name ?? $order->customer_name ?? 'Customer';
                $deliveryStreet  = $order->address;
                $deliveryUnit    = $order->unit ?? '';
                $deliveryCity    = $order->city;
                $deliveryProvince = $order->province;
                $deliveryPostal  = $order->postal_code;
                $deliveryContact = $order->contact_name ?? 'Customer';
                $deliveryPhone   = $order->phone ?? '604 524-0609';
                $deliveryEmail   = $order->email;
            }
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
                    'name' => $deliveryName,
                    'street' => $deliveryStreet,
                    'unit' => $deliveryUnit,
                    'city' => $deliveryCity,
                    'province' => $deliveryProvince,
                    'postalCode' => $deliveryPostal,
                    'country' => 'CAN',
                    'instructions' => 'Call 604-255-6007 if given phone is not responding',
                    'contact' => $deliveryContact,
                    'phone' => $deliveryPhone,
                    'notificationEmail' => $deliveryEmail,
                ],
                'serviceTypeId' => 4,
                'vehicleTypeId' => 1,
                'readyBy'=> $order->delivery_date,
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
//            dd($payload);
            Log::info('Pushing order to Novex', ['order_id' => $order->id, 'payload' => $payload]);

            $response = Http::withOptions([
                'verify' => config('services.http_verify'),
            ])->withHeaders([
                'Authorization' => 'Basic ' . config('services.novex.auth_key'),
                'Content-Type' => 'application/json'
            ])->post(config('services.novex.push_url'), $payload);

            if ($response->successful()) {
                $order->push = 1;
                $body = json_decode($response->getBody());
                $order->novex_order_id = $body->orderNumber ?? null;
                $order->save();

                return response()->json(['success' => true]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to push order',
                    'response' => $response->body()
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error('Novex Push Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while pushing the order'
            ], 500);
        }
    }




}
