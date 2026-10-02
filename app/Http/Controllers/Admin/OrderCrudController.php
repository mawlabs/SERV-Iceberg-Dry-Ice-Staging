<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Customer\CustomerDashboardController;
use App\Mail\CustomerRegisteredMail;
use App\Mail\DryIceOrderEmail;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\RecurringOrder;
use App\Models\StockMovement;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Carbon\Carbon;
use App\Mail\OrderPlacedMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\ClosestSupplierService;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Validator;


class OrderCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(Order::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/orders');
        CRUD::setEntityNameStrings('order', 'orders');
        CRUD::setListView('vendor.backpack.crud.order-list');
    }

    protected function setupListOperation()
    {
        CRUD::column('id')->label('Order #');
        CRUD::column('customer_name')->label('Customer Name');
        CRUD::addColumn([
            'name' => 'delivery_date',
            'type' => 'datetime',
            'label' => 'Delivery Date',
            'format' => 'YYYY-MM-DD HH:mm'
        ]);
        CRUD::column('status')->label('Status');
        CRUD::column('total_cost')->label('Total');
        CRUD::column('origin')->label('Origin');
        CRUD::column('recurring')->label('Recurring');

        // Sort by delivery date, newest first
        CRUD::orderBy('delivery_date', 'desc');
    }

    public function index()
    {
        $this->setupListOperation();
        $crud = $this->crud;

        // Get pagination parameters
        $perPage = request('per_page') ?? 10;
        $page = request('page', 1);

        // Get regular orders
        $ordersQuery = Order::query()
            ->select('id', 'invoice_id', 'customer_name', 'delivery_date', 'status', 'total_cost', 'origin', 'recurring', 'payment_status')
            ->when(request('status'), function($query, $status) {
                return $query->where('status', $status);
            })
            ->when(request('transfer_status'), function($query, $transferStatus) {
                return $query->where('push', $transferStatus);
            })
            ->when(request('recurring'), function($query, $recurring) {
                return $query->where('recurring', $recurring);
            })
            ->when(request('customer_id'), function($query, $customerId) {
                return $query->where('customer_id', $customerId);
            })
            ->when(request('payment_status'), function ($query, $paymentStatus) {
                if ($paymentStatus === 'paid') {
                    return $query->where('payment_status', 'paid');
                }
                if ($paymentStatus === 'pending') {
                    return $query->whereNull('payment_status');
                }
                return $query;
            });

        // Get recurring orders
        $recurringOrdersQuery = RecurringOrder::query()
            ->with('order')
            ->when(request('status'), function($query, $status) {
                return $query->where('status', $status);
            })
            ->when(request('customer_id'), function($query, $customerId) {
                return $query->whereHas('order', function($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
                });
            })
            ->when(request('payment_status'), function ($query, $paymentStatus) {
                if ($paymentStatus === 'paid') {
                    return $query->where('recurring_payment_status', 'paid'); // Fixed column name
                }
                if ($paymentStatus === 'pending') {
                    return $query->whereNull('recurring_payment_status'); // Fixed column name
                }
                return $query;
            });

        // Get all orders and recurring orders
        $orders = $ordersQuery->get();
        $recurringOrders = $recurringOrdersQuery->get();

        // Merge and sort by delivery date, newest first (id breaks ties). Recurring instances
        // use their own scheduled date, not the parent's template date. Order ids are not a
        // delivery sequence (imported/legacy orders get ids in import order), so never sort by id/invoice_id.
        $allOrders = collect()
            ->merge($orders)
            ->merge($recurringOrders)
            ->sortByDesc(function ($o) {
                $date = $o instanceof RecurringOrder ? $o->scheduled_delivery_date : $o->delivery_date;
                return [$date ? Carbon::parse($date)->toDateString() : '', $o->id];
            });

        // Manual pagination
        $total = $allOrders->count();
        $entries = new \Illuminate\Pagination\LengthAwarePaginator(
            $allOrders->forPage($page, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('vendor.backpack.crud.order-list', compact('entries', 'crud'));
    }

    public function setupCreateOperation()
    {
        CRUD::setValidation(OrderRequest::class);

        // Customer Details
        CRUD::addField([
            'name'  => 'customer_name',
            'label' => 'Customer Name',
            'type'  => 'text',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'email',
            'type'  => 'email',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name'  => 'phone',
            'type'  => 'text',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'amount_of_ice',
            'type'  => 'number',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name'  => 'amount_of_boxes',
            'type'  => 'number',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'origin',
            'type'  => 'select_from_array',
            'options' => ['online' => 'Online', 'manual' => 'Manual'],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name'  => 'recurring',
            'type'  => 'select_from_array',
            'options' => ['recurring' => 'Recurring', 'non-recurring' => 'Non-recurring'],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'location_name',
            'type'  => 'text',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        // Address Fields
        CRUD::addField([
            'name'  => 'address',
            'type'  => 'text',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'unit',
            'label' => 'Unit',
            'type'  => 'text',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name'  => 'city',
            'type'  => 'text',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'postal_code',
            'type'  => 'text',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name'  => 'province',
            'type'  => 'select_from_array',
            'options' => ['BC' => 'BC', 'AB' => 'AB'],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'country',
            'label' => 'Country',
            'type'  => 'text',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name'  => 'pickup_delivery',
            'type'  => 'select_from_array',
            'options' => ['pickup' => 'Pickup', 'delivery' => 'Delivery'],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'status',
            'type'  => 'select_from_array',
            'options' => ['valid' => 'Valid', 'skip' => 'Skip', 'cancelled' => 'Cancelled'],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name'  => 'delivery_date',
            'type'  => 'datetime',
            'datetime_picker_options' => [
                'format' => 'YYYY-MM-DD HH:mm'
            ],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::addField([
            'name'  => 'total_cost',
            'type'  => 'number',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        CRUD::addField([
            'name'  => 'notes',
            'type'  => 'textarea',
            'wrapperAttributes' => ['class' => 'form-group col-md-12'],
        ]);


    }



    // In your OrderCrudController or similar

    public function ajaxCreate(Request $request)
    {

        if($request->origin == 'manual'){
            return $this->handleManualOrderCreate($request);
        }

        // ✅ Check products BEFORE validation
        if (!$request->has('products') || !is_array($request->products) || empty($request->products)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Products are required'
            ], 422);
        }

        $rules = [
            'customer_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'amount_of_ice' => 'nullable|numeric|min:0',
            'amount_of_boxes' => 'nullable|numeric|min:0',
            'recurring' => 'string|in:recurring,non-recurring',
            'origin' => 'nullable|string|max:100',
            'location_name' => 'nullable|max:255',
            'address' => 'required|string|max:255',
            'unit' => 'nullable|string|max:50',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'province' => 'required|string|max:50',
            'country' => 'required|string|max:100',
            'delivery_date' => 'nullable',
            'delivery_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'required|string|in:valid,cancelled,skip',
            'pickup_delivery' => 'required|string|in:pickup,delivery',
            'supplier_id' => 'nullable|numeric|min:0',
            'hazmat' => 'nullable|numeric|min:0',
            'address_id' => 'nullable|exists:customer_addresses,id',
            'address_label' => 'nullable|string|max:100',
            'delivery_instructions' => 'nullable|string',
            'save_new_address' => 'nullable|boolean',
            'set_as_default' => 'nullable|boolean',
            'products' => 'required|array|min:1',
            'products.*' => 'numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $hasProducts = false;
        foreach ($request->products as $amount) {
            if ($amount > 0) {
                $hasProducts = true;
                break;
            }
        }

        if (!$hasProducts) {
            return response()->json([
                'status' => 'error',
                'message' => 'At least one product with amount greater than 0 is required'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Handle customer lookup or creation
            $existing_customer = Customer::where('email', $request->email)->first();

            if (!$existing_customer) {
                $customer = $this->handleCustomerData($request);
            } else {
                $customer = $existing_customer;
            }

            // Handle Address Management
            $addressId = $this->handleAddressManagement($request, $customer->id);

            // Add address_id to validated data
            $validated['address_id'] = $addressId;

            // Calculate costs server-side
            $subtotal = 0;
            foreach ($request->products as $productId => $amount) {
                if ($amount > 0) {
                    $product = Product::find($productId);
                    if ($product) {
                        $subtotal += $amount * $product->price;
                    }
                }
            }

            $delivery_cost = $validated['delivery_cost'] ?? 0;
            $hazmat_cost = $validated['hazmat'] ?? 0;
            $tax = $subtotal * 0.15;

            // Add calculated fields
            $validated['sub_total'] = $subtotal;
            $validated['tax'] = $tax;
            $validated['total_cost'] = $subtotal + $tax + floatval($delivery_cost) + floatval($hazmat_cost);
            $validated['customer_id'] = $customer->id;
            $validated['origin'] = 'manual';

            // Create the order
            $order = Order::create($validated);

            // Create order items
            foreach ($request->products as $productId => $amount) {
                if ($amount > 0) {
                    $product = Product::find($productId);
                    if ($product) {
                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $productId,
                            'amount_of_items' => $amount,
                            'unit_price' => $product->price,
                            'total_price' => $amount * $product->price,
                        ]);
                    }
                }
            }

            // Create invoice
//            $invoiceService = new InvoiceService();
//            $originalInvoice = $invoiceService->createInvoiceForOrder($order);
//
//            $order->load('invoice');

            DB::commit();

            // ✅✅✅ CRITICAL FIX: Return response IMMEDIATELY ✅✅✅
            // Store the response
            $responseData = [
                'success' => true,
                'message' => 'Order created successfully',
                'order' => $order
            ];

            // Create the response object
            $response = response()->json($responseData);

            // ✅ Queue the email AFTER preparing response (using dispatch or queue)
            // Option 1: Using Laravel Queue (RECOMMENDED)
//            try {
//                Mail::to($order->email)->queue(new OrderPlacedMail($order));
//            } catch (\Exception $e) {
//                \Log::error('Email queue failed', [
//                    'order_id' => $order->id,
//                    'error' => $e->getMessage()
//                ]);
//            }

            // Option 2: If queue doesn't work, dispatch after response
            // This ensures response is sent before email processing
//            register_shutdown_function(function() use ($order) {
//                try {
//                    Mail::to($order->email)->send(new OrderPlacedMail($order));
//                } catch (\Exception $e) {
//                    \Log::error('Email send failed', [
//                        'order_id' => $order->id,
//                        'error' => $e->getMessage()
//                    ]);
//                }
//            });

            // Return response immediately
            return $response;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function handleManualOrderCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id'      => 'required|exists:customers,id',
            'product'       => 'required|exists:products,id',
            'amount'           => 'required|numeric|min:1',
            'pickup_delivery'  => 'required|in:pickup,delivery',
            'po_number'        => 'nullable|string|max:255',
            'recurring'        => 'required|in:recurring,non-recurring',
            'delivery_date'    => 'required|date',
            'delivery_time'    => 'required',
            'notes'            => 'nullable|string',
            'origin'           => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {

            DB::beginTransaction();

            $customer = Customer::findOrFail($request->customer_id);
            $product  = Product::findOrFail($request->product);

            if ($product && $product->id == 1 && $request->amount < 15) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Dry Ice Pellets requires a minimum order of 15 lbs.'
                ]);
            }

            $order = Order::create([
                'customer_name' => isset($customer->name) ? $customer->name : null,
                'email' => isset($customer->email) ? $customer->email : null,
                'phone' => isset($customer->phone) ? $customer->phone : null,
                'customer_id'      => $customer->id,
                'pickup_delivery'  => $request->pickup_delivery,
                'po'        => $request->po_number,
                'recurring'        => $request->recurring,
                'delivery_date'    => \Carbon\Carbon::parse($request->delivery_date),
                'notes'            => $request->notes,
                'origin'           => 'manual',
                'status'           => 'valid',
                'created_by' => auth()->user()->id,
            ]);

            OrderItem::create([
                'order_id'         => $order->id,
                'product_id'       => $product->id,
                'amount_of_items'  => $request->amount,
                'unit_price'       => $product->price,
            ]);

//            $invoiceService = new InvoiceService();
//            $originalInvoice = $invoiceService->createInvoiceForOrder($order);
//
//            $order->load('invoice');
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order'   => $order->load('customer')
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            \Log::error('Manual order creation failed', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle address management for order creation/update
     * Returns the address_id to be used in the order
     */
    protected function handleAddressManagement(Request $request, $customerId)
    {
        // If an address_id is provided, use it directly
        if ($request->address_id) {
            // Verify the address belongs to this customer
            $address = CustomerAddress::where('id', $request->address_id)
                ->where('customer_id', $customerId)
                ->first();

            if ($address) {
                return $address->id;
            }
        }

        // If save_new_address is checked, create a new address record
        if ($request->save_new_address) {
            // If setting as default, unset other defaults first
            if ($request->set_as_default) {
                CustomerAddress::where('customer_id', $customerId)
                    ->update(['is_default' => 0]);
            }

            $address = CustomerAddress::create([
                'customer_id' => $customerId,
                'address_label' => $request->address_label ?: 'Order Address',
                'location_name' => $request->location_name,
                'address' => $request->address,
                'unit' => $request->unit,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'province' => $request->province,
                'country' => $request->country ?? 'Canada',
                'delivery_instructions' => $request->delivery_instructions,
                'is_default' => $request->set_as_default ?? 0,
                'is_active' => 1
            ]);

            return $address->id;
        }

        // If neither address_id nor save_new_address, return null
        // Order will use inline address fields
        return null;
    }
    /**
     * Handle AJAX submission from review form
     */

    public function ajaxCreateFromReview(Request $request)
    {
        // Filter out items with null or zero amounts before validation
        $items = $request->input('items', []);
        $filteredItems = array_filter($items, function($item) {
            $amount = $item['amount_of_items'] ?? null;
            return !is_null($amount) && $amount !== '' && $amount > 0;
        });

        // Re-index the array to avoid gaps
        $filteredItems = array_values($filteredItems);

        // Update the request with filtered items
        $request->merge(['items' => $filteredItems]);

        // Validate the request
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'amount_of_ice' => 'nullable|numeric|min:0',
            'amount_of_boxes' => 'nullable|numeric|min:0',
            'recurring' => 'string|in:recurring,non-recurring',
            'location_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'required|string|max:20',
            'province' => 'required|string|max:50',
            'country' => 'string|max:100',
            'delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'string|in:valid,cancelled,skip',
            'pickup_delivery' => 'string|in:pickup,delivery',
            'supplier_id' => 'nullable|numeric|min:0',
            'sub_total' => 'nullable|numeric|min:0',
            'delivery_cost' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'accept' => 'required|accepted',
            'items' => 'required|array|min:1',
            'hazmat' => 'nullable|numeric|min:0',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.amount_of_items' => 'required|numeric|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'nullable|numeric|min:0',
        ]);



        DB::beginTransaction();

        try {
            // Step 2: Resolve products and compute authoritative totals server-side.
            // GST 5% applies broadly (products, delivery, hazmat); PST 7% applies
            // only to the Styrofoam box (unit='box') portion of the subtotal.
            // Confirmed by Tyler 2026-08-27. Tax is recomputed here rather than
            // trusting the posted 'tax'/'total_cost' values.
            $subTotal = 0;
            $pstableSubtotal = 0;
            $resolvedItems = [];

            foreach ($validated['items'] as $item) {
                $amount = $item['amount_of_items'];
                $unitPrice = $item['unit_price'] ?? 0;
                $totalPrice = $item['total_price'] ?? ($amount * $unitPrice);

                $product = Product::find($item['product_id']);

                if ($product && $product->id == 1 && $amount < 10) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Dry Ice Pellets requires a minimum order of 10 lbs.'
                    ]);
                }

                $subTotal += $totalPrice;
                if ($product && $product->unit === 'box') {
                    $pstableSubtotal += $totalPrice;
                }

                $resolvedItems[] = [
                    'product_id' => $item['product_id'],
                    'amount_of_items' => $amount,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ];
            }

            $deliveryCost = (float) ($validated['delivery_cost'] ?? 0);
            $hazmat = (float) ($validated['hazmat'] ?? 0);
            $gst = round(($subTotal + $deliveryCost + $hazmat) * 0.05, 2);
            $pst = round($pstableSubtotal * 0.07, 2);
            $tax = $gst + $pst;
            $totalCost = round($subTotal + $deliveryCost + $hazmat + $tax, 2);

            // Step 3: Create the order
            $order = Order::create([
                'customer_name' => $validated['customer_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'amount_of_ice' => $validated['amount_of_ice'] ?? 0,
                'amount_of_boxes' => $validated['amount_of_boxes'] ?? 0,
                'recurring' => $validated['recurring'] ?? 'non-recurring',
                'location_name' => $validated['location_name'],
                'address' => $validated['address'],
                'unit' => $validated['unit'],
                'city' => $validated['city'],
                'postal_code' => $validated['postal_code'],
                'province' => $validated['province'],
                'country' => $validated['country'] ?? 'Canada',
                'delivery_date' => Carbon::parse($validated['delivery_date'])->setTimeFrom(Carbon::now())->format('Y-m-d H:i:s'),
                'notes' => $validated['notes'],
                'status' => $validated['status'] ?? 'valid',
                'pickup_delivery' => $validated['pickup_delivery'],
                'supplier_id' => $validated['supplier_id'],
                'sub_total' => $subTotal,
                'tax' => $tax,
                'delivery_cost' => $deliveryCost,
                'total_cost' => $totalCost,
                'origin' => 'online',
                'hazmat' => $hazmat,
            ]);

            // Step 4: Create order items
            foreach ($resolvedItems as $resolvedItem) {
                $order->items()->create(array_merge(['order_id' => $order->id], $resolvedItem));
            }

            $invoiceService = new InvoiceService();
            $originalInvoice = $invoiceService->createInvoiceForOrder($order);

            $order->load('invoice');

            DB::commit();

            Mail::to($order->email)->send(new OrderPlacedMail($order));

//            $order->payment_status = 0;
//            $order->save();



            return response()->json([
                'success' => true,
                'message' => 'Online Order submitted successfully',
                'order' => $order->load('items'),
                'order_id' => $order->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();


            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 422);
        }
    }


    public function paymentRedirect($orderId)
    {
        try {
            $order = Order::with('invoice')->findOrFail($orderId);

            $paymentParams = $this->generatePaymentParams($order);

            return view('website.order.payment-redirect', compact('paymentParams'));

        } catch (\Exception $e) {
            \Log::error('Payment redirect failed: ' . $e->getMessage());
            return redirect()->route('home')->with('error', 'Order not found or payment setup failed.');
        }
    }

    protected function generatePaymentParams($order)
    {
        $x_login = env('EXACT_LOGIN_ID');
        $transaction_key = env('EXACT_TRANSACTION_KEY');
        $payment_url = env('EXACT_PAYMENT_URL');
        $x_amount = number_format($order->total_cost, 2, '.', '');
        $x_invoice_num = $order->invoice->invoice_number;
        $x_description = 'Order #' . $order->id;
        $x_email = $order->email;
        $x_first_name = $order->customer_name;
        $x_return_url = route('payments.callback');
        $x_fp_sequence = rand(1000, 100000) + 123456;


        $x_fp_timestamp = time();

        $hmac_data = $x_login . "^" . $x_fp_sequence . "^" . $x_fp_timestamp . "^" . $x_amount . "^" . 'CAD';
        $x_fp_hash = hash_hmac('MD5', $hmac_data, $transaction_key);

        return [
            'x_login' => $x_login,
            'x_amount' => $x_amount,
            'x_fp_timestamp' => $x_fp_timestamp,
            'x_fp_sequence' => $x_fp_sequence,
            'x_invoice_num' => $x_invoice_num,
            'x_description' => $x_description,
            'x_first_name' => $x_first_name,
            'x_email' => $x_email,
//            'x_receipt_link_text' => 'Payment Page',
            'x_receipt_link_url' => $x_return_url,
            'x_receipt_link_method' => 'AUTO-GET',
            'x_currency_code' => 'CAD',
            'x_fp_hash' => $x_fp_hash,
            'x_show_form' => 'PAYMENT_FORM',
            'x_test_request' => 'False',
            'payment_url' => $payment_url
        ];
    }
    /* Handle customer data for review form */

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }



    protected function handleCustomerData($data)
    {
        $customer = Customer::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['customer_name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'postal_code' => $data['postal_code'],
                'province' => $data['province'],
                'country' => $data['country']
            ]
        );
        return $customer;
    }

    protected function saveOrder($data)
    {
        $customer = $this->handleCustomerData($data);
        $data['customer_id'] = $customer->id;

        return Order::create($data);
    }

    // Override store method to save order and customer
    public function store()
    {
        $request = $this->crud->validateRequest();
        $data = $request->all();

        $order = $this->saveOrder($data);

        // Send confirmation email
        try {
            Mail::to($order->email)->send(new OrderPlacedMail($order));
        } catch (\Exception $e) {
            \Log::error('Failed to send order email: ' . $e->getMessage());
        }

        return $this->crud->performSaveAction();
    }


    /**
     * Show order details (AJAX endpoint)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderDetails(Request $request)
    {
        try {
            $invoiceId = $request->get('invoice_id');

            $invoice = Invoice::find($invoiceId);

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found'
                ]);
            }

            $customerInfo = $this->getCustomerInfo($invoice);

            if (!$customerInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer information not found'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => number_format($invoice->total_amount, 2),
                    'customer_name' => $customerInfo->customer_name,
                    'customer_email' => $customerInfo->email,
                ]
            ]);

        } catch (Exception $e) {
            \Log::error('Error fetching invoice payment details: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ]);
        }
    }

    private function getCustomerInfo($invoice)
    {
        $customerInfo = null;

        switch($invoice->invoiceable_type) {
            case 'App\\Models\\Order':
            case 'Model\\Order':
                $order = \App\Models\Order::where('invoice_id', $invoice->id)->first();
                if ($order) {
                    $customerInfo = $order;
                }
                break;

            case 'App\\Models\\RecurringOrder':
            case 'Model\\RecurringOrder':
                $recurringOrder = \App\Models\RecurringOrder::find($invoice->invoiceable_id);
                if ($recurringOrder) {
                    $order = \App\Models\Order::find($recurringOrder->order_id);
                    if ($order) {
                        $customerInfo = $order;
                    }
                }
                break;

            default:
                // If no specific type, try to find order by invoice_id as fallback
                $order = \App\Models\Order::where('invoice_id', $invoice->id)->first();
                if ($order) {
                    $customerInfo = $order;
                }
                break;
        }

        return $customerInfo;
    }




    public function initiatePayment(Request $request)
    {
        try {
            $invoiceId = $request->get('invoice_id');

            $invoice = Invoice::find($invoiceId);

            if (!$invoice) {
                return redirect()->back()->with('error', 'Invoice not found');
            }

            $customerInfo = $this->getCustomerInfo($invoice);

            if (!$customerInfo) {
                return redirect()->back()->with('error', 'Customer information not found');
            }

            $paymentParams = $this->buildExactPaymentParams($invoice, $customerInfo);

            // Create HTML form and auto-submit directly to Exact
            $form = '<html><body>';
            $form .= '<form id="exactForm" method="POST" action="' . $paymentParams['payment_url'] . '">';

            foreach ($paymentParams as $key => $value) {
                if ($key !== 'payment_url') {
                    $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
                }
            }

            $form .= '</form>';
            $form .= '<script>document.getElementById("exactForm").submit();</script>';
            $form .= '</body></html>';

            return response($form);

        } catch (Exception $e) {
            \Log::error('Error processing payment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Server error occurred while processing payment');
        }
    }

    protected function buildExactPaymentParams($invoice, $customerInfo)
    {
        $x_login = env('EXACT_LOGIN_ID');
        $transaction_key = env('EXACT_TRANSACTION_KEY');
        $payment_url = env('EXACT_PAYMENT_URL');
        $x_amount = number_format($invoice->total_amount, 2, '.', '');
        $x_invoice_num = $invoice->invoice_number;
        $x_description = 'Invoice #' . $invoice->invoice_number;
        $x_email = $customerInfo->email;
        $x_first_name = $customerInfo->customer_name;
        $x_return_url = route('payments.callback'); // You'll need to create this route

//        $x_relay_response = 'TRUE';
//        $x_receipt_link_method = 'REDIRECT';
//        $x_receipt_link_url = url(route('payments.callback'));

        $x_fp_sequence = rand(1000, 100000) + 123456;
        $x_fp_timestamp = time();

        $hmac_data = $x_login . "^" . $x_fp_sequence . "^" . $x_fp_timestamp . "^" . $x_amount . "^" . 'CAD';
        $x_fp_hash = hash_hmac('MD5', $hmac_data, $transaction_key);

        return [
            'x_login' => $x_login,
            'x_amount' => $x_amount,
            'x_fp_timestamp' => $x_fp_timestamp,
            'x_fp_sequence' => $x_fp_sequence,
            'x_invoice_num' => $x_invoice_num,
            'x_description' => $x_description,
            'x_first_name' => $x_first_name,
            'x_email' => $x_email,
//            'x_receipt_link_text' => 'Payment Page',
            'x_receipt_link_url' => $x_return_url,
            'x_receipt_link_method' => 'AUTO-GET',
            'x_currency_code' => 'CAD',
            'x_fp_hash' => $x_fp_hash,
            'x_show_form' => 'PAYMENT_FORM',
            'x_test_request' => 'False',
//            'x_relay_response' => $x_relay_response,
//            'x_receipt_link_method' => $x_receipt_link_method,
//            'x_receipt_link_url' => $x_receipt_link_url,
            'payment_url' => $payment_url
        ];
    }


    public function handlePaymentCallback(Request $request) {
        $responseCode = $request->get('x_response_code');
        $invoiceNumber = $request->get('x_invoice_num');

        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->with('invoiceable')
            ->where('payment_status', Invoice::PENDING)
            ->first();

        if (!$invoice) {
            return view('website.order.payment-result', [
                'success' => false,
                'message' => 'Invoice not found or already processed.',
                'invoice_number' => $invoiceNumber ?? null,
            ]);
        }

        if ($responseCode == '1') {
            // success
            $invoice->update([
                'payment_status' => Invoice::PAID,
                'transaction_json' => json_encode($request->all()),
                'paid_at' => now()
            ]);

            $invoiceable = $invoice->invoiceable;

            if ($invoiceable instanceof Order) {
                $invoiceable->update(['payment_status' => 'paid']);
                Mail::to($invoiceable->email)->send(new OrderPlacedMail($invoiceable));
            } elseif ($invoiceable instanceof RecurringOrder) {
                $invoiceable->update(['recurring_payment_status' => 1]);
                $invoiceable->load('order');
                if ($invoiceable->order) {
                    Mail::to($invoiceable->order->email)->send(new OrderPlacedMail($invoiceable, 'recurring'));
                }
            }

            return view('website.order.payment-result', [
                'success' => true,
                'message' => 'Payment completed successfully!',
                'invoice_number' => $invoice->invoice_number,
                'amount' => $request->get('x_amount') ?? null,
                'transaction_id' => $request->get('x_trans_id') ?? null,
            ]);
        } else {
            // failed
            $invoice->update([
                'payment_status' => Invoice::FAILED,
                'transaction_json' => json_encode($request->all()),
                'paid_at' => now()
            ]);

            return view('website.order.payment-result', [
                'success' => false,
                'message' => 'Payment failed. Please try again.',
                'invoice_number' => $invoice->invoice_number,
            ]);
        }
    }


    /**
     * Update order via AJAX
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOrderAjax(Request $request, $id)
    {
        if($request->origin == 'manual'){
            return $this->handleManualOrderUpdate($request, $id);
        }
        try {
            $order = Order::findOrFail($id);

            // Validate the request data
            $rules = [
                'customer_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'products' => 'required|array',
                'products.*' => 'numeric|min:0',
                'recurring' => 'string|in:recurring,non-recurring',
                'origin' => 'nullable|string|max:100',
                'location_name' => 'nullable|string|max:255',
                'address' => 'required|string|max:255',
                'unit' => 'nullable|string|max:50',
                'city' => 'required|string|max:100',
                'postal_code' => 'required|string|max:20',
                'province' => 'required|string|max:50',
                'country' => 'required|string|max:100',
                'delivery_date' => 'nullable',
                'delivery_cost' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
                'status' => 'required|string|in:valid,cancelled,skip',
                'pickup_delivery' => 'required|string|in:pickup,delivery',
                'supplier_id' => 'nullable|numeric|min:0',
                'hazmat' => 'nullable|numeric|min:0',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validatedData = $validator->validated();

            // Validate at least one product has amount > 0
            $hasProducts = false;
            foreach ($request->products as $amount) {
                if ($amount > 0) {
                    $hasProducts = true;
                    break;
                }
            }

            if (!$hasProducts) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'At least one product with amount greater than 0 is required'
                ], 422);
            }

            DB::beginTransaction();

            // Calculate costs server-side using actual product prices from DB.
            // GST 5% applies broadly; PST 7% applies only to Styrofoam box
            // (unit='box') products. Confirmed by Tyler 2026-08-27.
            $subtotal = 0;
            $pstableSubtotal = 0;
            foreach ($request->products as $productId => $amount) {
                if ($amount > 0) {
                    $product = Product::find($productId);
                    if ($product) {
                        $lineTotal = $amount * $product->price;
                        $subtotal += $lineTotal;
                        if ($product->unit === 'box') {
                            $pstableSubtotal += $lineTotal;
                        }
                    }
                }
            }

            $delivery_cost = $validatedData['delivery_cost'];
            $hazmat_cost = $validatedData['hazmat'];
            $gst = round(($subtotal + floatval($delivery_cost) + floatval($hazmat_cost)) * 0.05, 2);
            $pst = round($pstableSubtotal * 0.07, 2);
            $tax = $gst + $pst;

            // Add calculated fields
            $validatedData['sub_total'] = $subtotal;
            $validatedData['tax'] = $tax;
            $validatedData['total_cost'] = $subtotal + $tax + floatval($delivery_cost) + floatval($hazmat_cost);

            // Keep original origin value
            $validatedData['origin'] = $order->origin;

            // Remove products from validated array as it's not an Order field
            unset($validatedData['products']);

            // Update order items
            foreach ($request->products as $productId => $amount) {
                $orderItem = OrderItem::where('product_id', $productId)->where('order_id', $order->id)->first();

                if ($amount > 0) {
                    $product = Product::find($productId);
                    if ($product) {
                        if ($orderItem) {
                            // Update existing order item
                            $orderItem->update([
                                'amount_of_items' => $amount,
                                'unit_price' => $product->price,
                                'total_price' => $amount * $product->price,
                            ]);
                        } else {
                            // Create new order item
                            OrderItem::create([
                                'order_id' => $order->id,
                                'product_id' => $productId,
                                'amount_of_items' => $amount,
                                'unit_price' => $product->price,
                                'total_price' => $amount * $product->price,
                            ]);
                        }
                    }
                } else {
                    // Remove order item if amount is 0
                    if ($orderItem) {
                        $orderItem->delete();
                    }
                }
            }

            // Update the order
            $order->update($validatedData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'order' => $order->fresh()
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating order: ' . $e->getMessage()
            ], 500);
        }
    }


    protected function handleManualOrderUpdate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'customer_id'      => 'required|exists:customers,id',
            'product'          => 'required|exists:products,id',
            'amount'           => 'required|numeric|min:1',
            'pickup_delivery'  => 'required|in:pickup,delivery',
            'po_number'        => 'nullable|string|max:255',
            'recurring'        => 'required|in:recurring,non-recurring',
            'delivery_date'    => 'required|date',
            'delivery_time'    => 'required',
            'notes'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        try {

            DB::beginTransaction();

            $order = Order::findOrFail($id);

            $customer = Customer::findOrFail($request->customer_id);
            $product  = Product::findOrFail($request->product);

            if ($product && $product->id == 1 && $request->amount < 15) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Dry Ice Pellets requires a minimum order of 15 lbs.'
                ]);
            }

            $order->update([
                'customer_name'    => $customer->name ?? null,
                'email'            => $customer->email ?? null,
                'phone'            => $customer->phone ?? null,
                'customer_id'      => $customer->id,
                'pickup_delivery'  => $request->pickup_delivery,
                'po'        => $request->po_number,
                'recurring'        => $request->recurring,
                'delivery_date'    => \Carbon\Carbon::parse($request->delivery_date),
                'notes'            => $request->notes,
            ]);

            $orderItem = OrderItem::where('order_id', $order->id)->first();

            if ($orderItem) {

                $orderItem->update([
                    'product_id'      => $product->id,
                    'amount_of_items' => $request->amount,
                    'unit_price'      => $product->price,
                ]);

            } else {

                OrderItem::create([
                    'order_id'        => $order->id,
                    'product_id'      => $product->id,
                    'amount_of_items' => $request->amount,
                    'unit_price'      => $product->price,
                ]);
            }

            // Regenerate invoice if needed
//            if ($order->invoice) {
//                $order->invoice->delete();
//            }
//
//            $invoiceService = new InvoiceService();
//            $invoiceService->createInvoiceForOrder($order->fresh());

            $order->load([
                'customer',
//                'invoice',
                'items.product'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'order'   => $order
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            \Log::error('Manual order update failed', [
                'order_id' => $id,
                'message'  => $e->getMessage(),
                'line'     => $e->getLine(),
                'file'     => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Delete order via AJAX
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteOrderAjax($id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting order: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getCustomerByEmail(Request $request)
    {
        $email = $request->get('email');

        if (!$email) {
            return response()->json(['error' => 'Email is required'], 400);
        }

        $customer = Customer::select([
            'name',
            'email',
            'phone',
            'address',
            'city',
            'postal_code',
            'province'
        ])->where('email', $email)->first();

        if ($customer) {
            return response()->json([
                'success' => true,
                'customer' => $customer
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Customer not found'
        ], 404);
    }

    /**
     * AJAX endpoint for customer search
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxCustomers(Request $request)
    {
        $search = $request->input('q');

        $results = Customer::query()
            ->where('id', 'like', "%{$search}%")
            ->orWhere('name', 'like', "%{$search}%")
            ->limit(20)
            ->get(['id', 'name']);

        // Return format Select2 expects: [{id: 1, text: 'Customer Name'}]
        $formatted = $results->map(function ($customer) {
            return ['id' => $customer->id, 'text' => $customer->name . ' (ID: ' . $customer->id . ')'];
        });

        return response()->json($formatted);
    }


    public function getCustomerAddresses($customerId)
    {
        try {
            $addresses = CustomerAddress::where('customer_id', $customerId)
                ->where('is_active', 1)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'addresses' => $addresses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load addresses: ' . $e->getMessage()
            ], 500);
        }
    }

    public function manualCreateModal()
    {
        $customers = Customer::orderBy('name')->get();

        // Some products (e.g. Styrofoam boxes) are public/credit-card only.
        $products = Product::where('available_to_account_holders', true)->get();

        // Default to the "blocks" product (Tyler's main account-holder product),
        // matched by name rather than a hard-coded id. Falls back to the first
        // available product if none match, so this never hard-fails.
        $defaultProduct = $products->first(fn ($p) => stripos($p->product_name, 'block') !== false)
            ?? $products->first();

        $defaultValues = [
            'customer_id'      => null,
            'product_id'       => $defaultProduct?->id,
            'amount'           => 0,
            'pickup_delivery'  => 'delivery',
            'po_number'        => '',
            'recurring'        => 'non-recurring',
            'delivery_date'    => now()->format('Y-m-d'),
            'delivery_time'    => '17:00',
            'notes'            => '',
        ];

        return view(
            'vendor.backpack.crud.inc.manual_order_modal',
            [
                'mode'            => 'create',
                'modalTitle'      => 'Create Manual Order',
                'saveButtonText'  => 'Create Order',
                'customers'       => $customers,
                'products'        => $products,
                'defaultValues'   => $defaultValues,
                'order'           => null,
            ]
        );
    }

    public function manualEditModal($id)
    {
        $order = Order::with([
            'items.product',
            'customer'
        ])->findOrFail($id);

        if ($order->origin !== 'manual') {
            abort(404);
        }

        $customers = Customer::orderBy('name')->get();

        $item = $order->items->first();

        // Some products (e.g. Styrofoam boxes) are public/credit-card only,
        // but keep an already-selected product visible so an existing order
        // can still be edited/saved without silently changing its product.
        $products = Product::where('available_to_account_holders', true)
            ->when($item?->product_id, fn ($q) => $q->orWhere('id', $item->product_id))
            ->get();

        $defaultValues = [
            'customer_id'      => $order->customer_id,
            'product_id'       => $item?->product_id,
            'amount'           => $item?->amount_of_items ?? 1,
            'pickup_delivery'  => $order->pickup_delivery,
            'po_number'        => $order->po ?? '',
            'recurring'        => $order->recurring,
            'delivery_date'    => $order->delivery_date
                ? \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d')
                : '',
            'delivery_time'    => $order->delivery_date
                ? \Carbon\Carbon::parse($order->delivery_date)->format('H:i')
                : '',
            'notes'            => $order->notes,
        ];

        return view(
            'vendor.backpack.crud.inc.manual_order_modal',
            [
                'mode'            => 'edit',
                'modalTitle'      => 'Edit Manual Order',
                'saveButtonText'  => 'Update Order',
                'customers'       => $customers,
                'products'        => $products,
                'defaultValues'   => $defaultValues,
                'order'           => $order,
            ]
        );
    }

    public function modalCreate()
    {
        $products = Product::all();

        $defaultValues = [
            'invoice_id' => '',
            'customer_id' => null,
            'customer_email' => '',
            'customer_name' => '',
            'customer_phone' => '',
            'ice_amount' => 0,
            'box_amount' => 0,
            'recurring' => 'non-recurring',
            'location_name' => '',
            'origin' => 'manual',
            'address' => '',
            'unit' => '',
            'city' => '',
            'postal_code' => '',
            'province' => '',
            'country' => 'Canada',
            'pickup_delivery' => 'delivery',
            'status' => 'valid',
            'delivery_date' => '',
            'delivery_time' => '',
            'notes' => '',
            'delivery_cost' => 0,
            'sub_total' => 0,
            'tax' => 0,
            'total_cost' => 0,
            'hazmat' => 12,
            'novex_pushed' => false,
            // Address fields
            'address_id' => null,
            'address_label' => '',
            'delivery_instructions' => '',
            'products' => []
        ];

        return view('vendor.backpack.crud.inc.order_modal', [
            'mode' => 'create',
            'modalTitle' => 'Create New Order',
            'saveButtonText' => 'Create Order',
            'showOrderId' => false,
            'showDeleteButton' => false,
            'showPushButton' => false,
            'defaultValues' => $defaultValues,
            'products' => $products,
            'order' => null
        ]);
    }

    public function editModal($id, Request $request)
    {
        $recurring = intval($request->query('recurring'));

        if($recurring){
            $recurring_id = intval($request->query('recurring_id'));
            $order = Order::where('recurring', Order::RECURRING)
                ->where('id', $id)
                ->with(['recurringOrders' => function ($query) use ($recurring_id) {
                    $query->where('id',$recurring_id);
                }])
                ->with(['items','items.product'])
                ->get()
                ->first();

            $status = null;
            if ($order && $order->recurringOrders?->first()->novex_order_id) {
                $cacheKey = 'order_status_' . $order->recurringOrders?->first()->novex_order_id;
                $status = Cache::remember($cacheKey, now()->addHours(3), function () use ($order) {
                    return $this->getOrderStatus($order->recurringOrders?->first()->novex_order_id);
                });
            }

            $data = [
                'order' => $order,
                'recurring' => $recurring,
                'status' => $status,
            ];
//            dd($data);
            return view('website.customer.partials.order_details', $data);
        }

        // ✅ Load order with RENAMED relationship
        $order = Order::whereId($id)
            ->with(['items.product', 'customerAddress', 'customer']) // Changed from 'address' to 'customerAddress'
            ->first();

        // Check if order exists
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $orderProducts = $order->items;

        // Debug logging
        \Log::info('Edit Modal - Order Data', [
            'order_id' => $order->id,
            'items_count' => $orderProducts->count(),
            'address_id' => $order->address_id,
            'has_address_relationship' => $order->customerAddress ? 'yes' : 'no',
            'address_column' => $order->address, // This is now the column value
        ]);

        // ✅ UPDATED: Check for customerAddress relationship
        $hasAddressRelationship = $order->address_id && $order->customerAddress;

        $data = [
            'mode' => 'edit',
            'order' => $order,
            'modalTitle' => 'Edit CC Order',
            'saveButtonText' => 'Update Order',
            'showOrderId' => true,
            'showDeleteButton' => false,
            'showPushButton' => $order->status == Order::COMPLETED ? false : true,
            'products' => $orderProducts,
            'defaultValues' => [
                'order_id' => $order->id,
                'invoice_id' => $order->invoice_id,
                'customer_id' => $order->customer_id,
                'customer_email' => $order->email,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->phone,
                'ice_amount' => $order->amount_of_ice,
                'box_amount' => $order->amount_of_boxes ?? 0,
                'recurring' => $order->recurring,

                // ✅ Address fields - using customerAddress relationship
                'address_id' => $order->address_id,
                'location_name' => $hasAddressRelationship
                    ? $order->customerAddress->location_name
                    : $order->location_name,
                'address' => $hasAddressRelationship
                    ? $order->customerAddress->address
                    : $order->address, // Now correctly references the column
                'unit' => $hasAddressRelationship
                    ? $order->customerAddress->unit
                    : $order->unit,
                'city' => $hasAddressRelationship
                    ? $order->customerAddress->city
                    : $order->city,
                'postal_code' => $hasAddressRelationship
                    ? $order->customerAddress->postal_code
                    : $order->postal_code,
                'province' => $hasAddressRelationship
                    ? $order->customerAddress->province
                    : $order->province,
                'country' => $hasAddressRelationship
                    ? ($order->customerAddress->country ?? 'Canada')
                    : ($order->country ?? 'Canada'),
                'address_label' => $hasAddressRelationship
                    ? ($order->customerAddress->address_label ?? '')
                    : '',
                'delivery_instructions' => $hasAddressRelationship
                    ? ($order->customerAddress->delivery_instructions ?? '')
                    : '',

                // Order details
                'delivery_date' => $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('Y-m-d') : '',
                'delivery_time' => $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('H:i') : '',
                'notes' => $order->notes,
                'status' => $order->status ?? 'valid',
                'pickup_delivery' => $order->pickup_delivery,
                'sub_total' => $order->sub_total ?? 0,
                'delivery_cost' => $order->delivery_cost ?? 0,
                'tax' => $order->tax ?? 0,
                'total_cost' => $order->total_cost ?? 0,
                'origin' => $order->origin,
                'novex_pushed' => $order->push ?? 0,
                'hazmat' => $order->hazmat ?? 0,
                'products' => []
            ]
        ];

        return view('vendor.backpack.crud.inc.order_modal', $data);
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


    public function getOrdersByLocationAndDate(Request $request)
    {
        try {
            $request->validate([
                'location' => 'required|string',
                'delivery_date' => 'required|date'
            ]);

            $location = $request->input('location');
            $deliveryDate = $request->input('delivery_date');

            // Fetch orders from database where delivery_location and delivery_date match
            $orders = Order::whereRaw('DATE(delivery_date) = ?', [$deliveryDate])
                ->with('items.product')
//                ->where('delivery_location', $location)
                ->orderBy('created_at', 'asc')
                ->get([
                    'id',
                    'customer_name',
                    'amount_of_ice',
                    'amount_of_boxes',
                    'email',
                    'phone',
//                    'delivery_location',
                    'delivery_date',
                    'created_at'
                ]);

            return response()->json($orders, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch orders',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function sendDryIceOrderEmail(Request $request)
    {
        try {
            $request->validate([
                'location' => 'required|string',
                'to' => 'required|email',
                'subject' => 'required|string',
                'body' => 'required|string',
                'delivery_date' => 'required|date'
            ]);

            $location = $request->input('location');
            $recipientEmail = $request->input('to');
            $subject = $request->input('subject');
            $body = $request->input('body');
            $deliveryDate = $request->input('delivery_date');

            $orders = Order::whereRaw('DATE(delivery_date) = ?', [$deliveryDate])
//                ->where('delivery_location', $location)
                ->orderBy('created_at', 'asc')
                ->get();


            Mail::to($recipientEmail)->send(new DryIceOrderEmail([
                'location' => $location,
                'delivery_date' => $deliveryDate,
                'subject' => $subject,
                'body' => $body,
                'orders' => $orders,
                'recipient_email' => $recipientEmail
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => [
                    'location' => $location,
                    'recipient' => $recipientEmail,
                    'delivery_date' => $deliveryDate,
                    'orders_count' => $orders->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send email',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function viewInvoice($invoiceId)
    {
        $invoice     = Invoice::findOrFail($invoiceId);
        $invoiceable = $invoice->invoiceable;

        // If it's a RecurringOrder, load its parent Order for email/customer data
        if ($invoiceable instanceof \App\Models\RecurringOrder) {
            $invoiceable->load('order'); // eager load the parent order
            $order = $invoiceable->order;
        } else {
            $order = $invoiceable; // it's already an Order
        }

        if (request()->ajax()) {
            return view('emails.invoice-pdf', [
                'order'          => $order,
                'invoice_number' => $invoice->invoice_number ?? null,
                'invoiceable'    => $invoiceable, // pass recurring order too if needed in view
            ])->render();
        }

        abort(403);
    }

    public function downloadInvoice($invoiceId)
    {
        $invoice     = Invoice::findOrFail($invoiceId);
        $invoiceable = $invoice->invoiceable;

        if ($invoiceable instanceof \App\Models\RecurringOrder) {
            $invoiceable->load('order');
            $order = $invoiceable->order;
        } else {
            $order = $invoiceable;
        }

        $pdf = Pdf::loadView('emails.invoice-pdf', [
            'order'          => $order,
            'invoice_number' => $invoice->invoice_number ?? null,
            'invoiceable'    => $invoiceable,
        ])->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('chroot', public_path());

        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }
}
