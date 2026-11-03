<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ManualPaymentRequest;
use App\Mail\ConsolidatedInvoiceMail;
use App\Models\ManualPayment;
use Illuminate\Support\Facades\DB;
use App\Mail\OrderPlacedMail;
use App\Models\Invoice;
use App\Models\InvoiceOrders;
use App\Models\RecurringOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Mail;

/**
 * Class ManualPaymentCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ManualPaymentCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\ManualPayment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/manual-payments');
        CRUD::setEntityNameStrings('manual payment', 'manual payments');
        CRUD::setCreateView('vendor.backpack.base.manual_payments');
    }


    public function index()
    {
        $this->crud->hasAccessOrFail('list');


        $payments = ManualPayment::with('invoice')->orderBy('id', 'desc')->get();

        return view('vendor.backpack.crud.manualPayments-list', [
            'payments' => $payments
        ]);
    }


    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::setFromDb();
        $this->crud->removeAllButtonsFromStack('line');
        $this->crud->addButtonFromView('line', 'view_button', 'view-button', 'beginning');
//        dd(\App\Models\ManualPayment::all());
        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ManualPaymentRequest::class);

        CRUD::field('contact_name')->type('text')->label('Contact Name');
        CRUD::field('email')->type('email');
        CRUD::field('order_number')->type('text');
        CRUD::field('description')->type('textarea');
        CRUD::field('amount')->type('number');
    }

    public function initiatePayment(Request $request)
    {
        try {
            $paymentMethod = $request->get('payment_method');
            $invoiceNumber = $request->get('invoice_id');
            // Find invoice
            $invoice = Invoice::where('invoice_number', $invoiceNumber)
                ->whereIn('payment_status', [Invoice::PENDING, Invoice::FAILED])
                ->first();

            if (!$invoice) {
                return redirect()->back()->with('error', 'Invoice not found or already processed');
            }

            if ($paymentMethod === 'credit') {
                // Process credit card payment
                $customerInfo = $this->resolveCustomerInfo($invoice);

                if (!$customerInfo) {
                    return redirect()->back()->with('error', 'Customer information not found');
                }

                $paymentParams = $this->buildExactPaymentParams($invoice, $customerInfo);

                // Create HTML form and auto-submit directly to Exact
                                $form = '<html><head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 100vh;
                        background-color: #f8f9fa;
                    }
                    .loader {
                        text-align: center;
                    }
                    .spinner {
                        border: 4px solid #e9ecef;
                        border-top: 4px solid #007bff;
                        border-radius: 50%;
                        width: 50px;
                        height: 50px;
                        animation: spin 1s linear infinite;
                        margin: 0 auto 10px;
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    h2 {
                        font-weight: normal;
                        color: #333;
                    }
                </style>
                </head><body>';
                $form .= '<div class="loader">
                    <div class="spinner"></div>
                    <h2>Redirecting to payment gateway...</h2>
                    <p>Please wait while we securely connect you.</p>
                </div>';

                $form .= '<form id="exactForm" method="POST" action="' . $paymentParams['payment_url'] . '">';

                foreach ($paymentParams as $key => $value) {
                    if ($key !== 'payment_url') {
                        $form .= '<input type="hidden" name="' . $key . '" value="' . htmlspecialchars($value) . '">';
                    }
                }

                $form .= '</form>';
                $form .= '<script>
                    setTimeout(() => {
                        document.getElementById("exactForm").submit();
                    }, 1200);
                </script>';
                $form .= '</body></html>';

                return response($form);

            } else {
                // Process other payment methods - call store method
                // Pass invoice_id directly without validation conflicts
                $storeRequest = new Request([
                    'invoice_id' => $invoice->id,
                    'contact_name' => $request->get('contact_name'),
                    'email' => $request->get('email'),
                    'description' => $request->get('description'),
                    'amount' => $request->get('amount'),
                    'payment_method' => 'other'
                ]);

                return $this->storeManualPayment($storeRequest, $invoice);
            }

        } catch (Exception $e) {
            \Log::error('Error processing payment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Server error occurred while processing payment');
        }
    }

    public function store(Request $request)
    {
        $this->crud->hasAccessOrFail('create');

        $validatedRequest = $this->crud->validateRequest();
        $data = $validatedRequest->all();

        $invoice = Invoice::where('invoice_number', $data['invoice_id'])
            ->with('invoiceable')
            ->whereIn('payment_status', [Invoice::PENDING, Invoice::FAILED])
            ->first();

        if (!$invoice) {
            \Alert::error('Invoice not found or already paid.')->flash();
            return redirect()->back()->withInput();
        }

        return $this->storeManualPayment($request, $invoice);
    }

    protected function storeManualPayment($request, $invoice)
    {
        $data = $request->all();

        try {
            DB::beginTransaction();

            // Create manual payment record
            ManualPayment::create([
                'invoice_id'   => $invoice->id,
                'contact_name' => $data['contact_name'] ?? null,
                'email'        => $data['email'] ?? null,
                'description'  => $data['description'] ?? null,
                'amount'       => $data['amount'] ?? $invoice->total_amount,
            ]);

            // Mark invoice + all underlying orders paid (handles legacy AND consolidated)
            $this->applyPayment($invoice);

            DB::commit();

            \Alert::success('Manual payment created and order updated.')->flash();

        } catch (\Throwable $e) {
            DB::rollback();
            \Log::error('Manual payment failed: ' . $e->getMessage());
            \Alert::error('Error processing payment: ' . $e->getMessage())->flash();
            return redirect()->back()->withInput();
        }

        return redirect($this->crud->route);
    }

    /**
     * Resolve {email, customer_name} for any invoice shape.
     * - Consolidated invoice (invoiceable_type === null): use the directly attached
     *   customer (invoices.customer_id), mapping Customer.name -> customer_name.
     *   Falls back to the first linked order if customer_id is missing.
     * - Legacy polymorphic invoice: read from the related Order / RecurringOrder.
     * Returns null only if nothing can be resolved (caller handles gracefully).
     */
    protected function resolveCustomerInfo($invoice)
    {
        // ---- Consolidated invoice ----
        if (empty($invoice->invoiceable_type)) {
            $customer = $invoice->customer; // belongsTo Customer via customer_id

            if ($customer) {
                return (object) [
                    'email'         => $customer->email,
                    'customer_name' => $customer->name,
                ];
            }

            // Fallback: derive from the first linked order in the pivot
            $order = $this->firstLinkedOrder($invoice);
            if ($order) {
                return (object) [
                    'email'         => $order->email,
                    'customer_name' => $order->customer_name,
                ];
            }

            return null;
        }

        // ---- Legacy polymorphic invoice ----
        $invoiceable = $invoice->invoiceable;

        if ($invoiceable instanceof Order) {
            return (object) [
                'email'         => $invoiceable->email,
                'customer_name' => $invoiceable->customer_name,
            ];
        }

        if ($invoiceable instanceof RecurringOrder) {
            $invoiceable->loadMissing('order');
            if ($invoiceable->order) {
                return (object) [
                    'email'         => $invoiceable->order->email,
                    'customer_name' => $invoiceable->order->customer_name,
                ];
            }
        }

        return null;
    }

    /**
     * Mark an invoice and all of its underlying orders as paid.
     * Idempotent and exception-safe: a failure to update one entity (or to send
     * mail) is logged and never aborts the surrounding payment transaction.
     *
     * - Legacy polymorphic invoice  -> the single invoiceable (Order/RecurringOrder),
     *   keeps the existing confirmation email.
     * - Consolidated invoice        -> every order linked via the invoice_orders
     *   pivot (plus a safety sweep on orders.invoice_id). NO auto email — those
     *   account-holder invoices are sent manually (scope Q8).
     */
    protected function applyPayment(Invoice $invoice): void
    {
        // 1) The invoice itself
        $invoice->update([
            'payment_status' => Invoice::PAID,
            'paid_at'        => now()->format('Y:m:d H:i:s'),
        ]);
        // 2) Legacy polymorphic invoice — single related entity, keep email
        if (!empty($invoice->invoiceable_type) && !empty($invoice->invoiceable_id)) {
            $invoiceable = $invoice->invoiceable;

            if ($invoiceable instanceof Order) {
                $invoiceable->update(['payment_status' => 'paid']);
                $this->safeSendOrderMail($invoiceable->email, $invoiceable);
            } elseif ($invoiceable instanceof RecurringOrder) {
                $invoiceable->update(['recurring_payment_status' => 1]);
                $invoiceable->loadMissing('order');
                if ($invoiceable->order) {
                    $this->safeSendOrderMail($invoiceable->order->email, $invoiceable, 'recurring');
                }
            }

            return;
        }

        // 3) Consolidated invoice — mark every linked order, no auto email
        InvoiceOrders::where('invoice_id', $invoice->id)->get()
            ->each(function ($link) {
                try {
                    if ($link->invoiceable_type === RecurringOrder::class) {
                        RecurringOrder::where('id', $link->invoiceable_id)
                            ->update(['recurring_payment_status' => 1]);
                    } else {
                        Order::where('id', $link->invoiceable_id)
                            ->update(['payment_status' => 'paid']);
                    }
                } catch (\Throwable $e) {
                    \Log::error('applyPayment link update failed: ' . $e->getMessage());
                }
            });

        // Safety sweep: catch any order stamped with this invoice_id but missing
        // from the pivot. Idempotent — re-marking a paid order is harmless.
        Order::where('invoice_id', $invoice->id)->update(['payment_status' => 'paid']);
        RecurringOrder::where('invoice_id', $invoice->id)->update(['recurring_payment_status' => 1]);

        // Uncomment to auto-send on payment.
        // $this->sendConsolidatedInvoiceEmail($invoice);
    }

    /**
     * Email a consolidated invoice (with PDF attached) to its customer.
     */
    protected function sendConsolidatedInvoiceEmail(Invoice $invoice): void
    {
        try {
            $info  = $this->resolveCustomerInfo($invoice);
            $email = $info->email ?? ($invoice->customer->email ?? null);

            if (empty($email)) {
                \Log::warning('Consolidated invoice email skipped — no customer email for invoice ' . $invoice->id);
                return;
            }

            $invoice->loadMissing(['lineItems.product', 'lineItems.order', 'flatCharges']);
            $invoiceOrders = InvoiceOrders::where('invoice_id', $invoice->id)->get();

            $pdfData = Pdf::loadView('emails.invoice-pdf-consolidated', [
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
            ])->output();

            Mail::to($email)->send(new ConsolidatedInvoiceMail($invoice, $invoice->customer, $pdfData));

            $this->logInvoiceEmail($invoice->id, $email, \App\Models\InvoiceEmailLog::STATUS_SENT);
        } catch (\Throwable $e) {
            \Log::error('Consolidated invoice email failed: ' . $e->getMessage());

            $this->logInvoiceEmail($invoice->id, $email ?? null, \App\Models\InvoiceEmailLog::STATUS_FAILED, $e->getMessage());
        }
    }

    /**
     * Record an invoice email send attempt. Deliberately never throws -- a
     * logging failure must never affect the send flow.
     */
    private function logInvoiceEmail($invoiceId, ?string $sentTo, string $status, ?string $errorMessage = null): void
    {
        try {
            \App\Models\InvoiceEmailLog::create([
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

    /**
     * Send the order/recurring confirmation email without ever breaking the
     * payment flow (missing address or mail-server issues are logged, not thrown).
     */
    protected function safeSendOrderMail($email, $invoiceable, $type = null): void
    {
        if (empty($email)) {
            return;
        }

        try {
            $mail = $type
                ? new OrderPlacedMail($invoiceable, $type)
                : new OrderPlacedMail($invoiceable);

            Mail::to($email)->send($mail);
        } catch (\Throwable $e) {
            \Log::error('Order confirmation mail failed: ' . $e->getMessage());
        }
    }

    /**
     * First order linked to a consolidated invoice via the pivot (for fallbacks).
     */
    protected function firstLinkedOrder($invoice)
    {
        $link = InvoiceOrders::where('invoice_id', $invoice->id)->first();

        if (!$link) {
            return null;
        }

        if ($link->invoiceable_type === RecurringOrder::class) {
            return optional(RecurringOrder::find($link->invoiceable_id))->order;
        }

        return Order::find($link->invoiceable_id);
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
        $x_return_url = route('manual-payments.callback');

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
            'x_receipt_link_url' => $x_return_url,
            'x_receipt_link_method' => 'AUTO-GET',
            'x_currency_code' => 'CAD',
            'x_fp_hash' => $x_fp_hash,
            'x_show_form' => 'PAYMENT_FORM',
            'x_test_request' => 'False',
            'payment_url' => $payment_url
        ];
    }

    public function handlePaymentCallback(Request $request)
    {
        $responseCode = $request->get('x_response_code');
        $invoiceNumber = $request->get('x_invoice_num');
        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->with('invoiceable')
            ->whereIn('payment_status', [Invoice::PENDING, Invoice::FAILED])
            ->first();

        if (!$invoice) {
            return view('vendor.backpack.base.payment-result', [
                'success' => false,
                'message' => 'Invoice not found or already processed.',
                'invoice_number' => $invoiceNumber ?? null,
            ]);
        }

        if ($responseCode == '1') {
            try {
                DB::beginTransaction();

                // Create manual payment record for credit card payment
                ManualPayment::create([
                    'invoice_id' => $invoice->id,
                    'contact_name' => $request->get('x_first_name') ?? 'N/A',
                    'email' => $request->get('x_email') ?? 'N/A',
                    'description' => 'Credit Card Payment - Transaction ID: ' . $request->get('x_trans_id'),
                    'amount' => $request->get('x_amount'),
                    'payment_method' => 'credit_card',
                    'transaction_id' => $request->get('x_trans_id'),
                ]);
                // Store the gateway response, then mark invoice + orders paid
                // (applyPayment handles both legacy and consolidated invoices)
                $invoice->update(['transaction_json' => json_encode($request->all())]);
                $this->applyPayment($invoice);

                DB::commit();

                return view('vendor.backpack.base.payment-result', [
                    'success' => true,
                    'message' => 'Payment completed successfully!',
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $request->get('x_amount') ?? null,
                    'transaction_id' => $request->get('x_trans_id') ?? null,
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                \Log::error('Error processing credit card payment callback: ' . $e->getMessage());

                return view('vendor.backpack.base.payment-result', [
                    'success' => false,
                    'message' => 'Error processing payment. Please contact support.',
                    'invoice_number' => $invoice->invoice_number,
                ]);
            }
        } else {
            // failed
            $invoice->update([
                'payment_status' => Invoice::FAILED,
                'transaction_json' => json_encode($request->all()),
                'paid_at' => now()
            ]);

            return view('vendor.backpack.base.payment-result', [
                'success' => false,
                'message' => 'Payment failed. Please try again.',
                'invoice_number' => $invoice->invoice_number,
            ]);
        }
    }

//    public function Store(Request $request)
//    {
//        $this->crud->hasAccessOrFail('create');
//
//        $request = $this->crud->validateRequest();
//        $data = $request->all();
//
//        $invoice = Invoice::where('id', $data['invoice_id'])->with('invoiceable')
//            ->whereIn('payment_status', [Invoice::PENDING, Invoice::FAILED])
//            ->first();
//
//        if (!$invoice) {
//            \Alert::error('Invoice not found or already paid.')->flash();
//            return redirect()->back()->withInput();
//        }
//
//        try {
//            DB::beginTransaction();
//
//            // Create manual payment record
//            ManualPayment::create([
//                'invoice_id' => $invoice->id,
////                'invoice_number' => $data['invoice_id'],
//                'contact_name' => $data['contact_name'],
//                'email' => $data['email'],
//                'description' => $data['description'],
//                'amount' => $data['amount'],
//            ]);
//
//            // Update invoice payment status
//            $invoice->update([
//                'payment_status' => Invoice::PAID,
////                'paid_at' => now()
//            ]);
//
//            $invoiceable = $invoice->invoiceable;
//
//            if ($invoiceable instanceof Order) {
//                // Update direct order
//                $invoiceable->update(['payment_status' => 'paid']);
//                Mail::to($invoiceable->email)->send(new OrderPlacedMail($invoiceable));
//            } elseif ($invoiceable instanceof RecurringOrder) {
//                // Update recurring order
//                $invoiceable->update(['recurring_payment_status' => 1]);
//                $invoiceable->load('order');
//                if ($invoiceable->order) {
//                    Mail::to($invoiceable->order->email)->send(new OrderPlacedMail($invoiceable, 'recurring'));
//                }
//            }
//
//            DB::commit();
//
//            \Alert::success('Manual payment created and order updated.')->flash();
//
//        } catch (\Exception $e) {
//            DB::rollback();
//            \Alert::error('Error processing payment: ' . $e->getMessage())->flash();
//            return redirect()->back()->withInput();
//        }
//
//        return redirect($this->crud->route);
//    }



//    public function generateExactFields(Request $request)
//    {
//        $x_login = env('EXACT_LOGIN_ID');
//        $transaction_key = env('EXACT_TRANSACTION_KEY');
//        $x_currency_code = 'CAD';
//        $x_return_url = route('manual-payments.callback');
//
//        // Generate security fields like in your PHP code
//        srand(time());
//        $x_fp_sequence = rand(1000, 100000) + 123456;
//        $x_fp_timestamp = time();
//        $x_amount = $request->amount;
//
//        $hmac_data = $x_login . "^" . $x_fp_sequence . "^" . $x_fp_timestamp . "^" . $x_amount . "^" . $x_currency_code;
//        $x_fp_hash = hash_hmac('MD5', $hmac_data, $transaction_key);
//
//        return response()->json([
//            'x_fp_sequence' => $x_fp_sequence,
//            'x_fp_timestamp' => $x_fp_timestamp,
//            'x_fp_hash' => $x_fp_hash,
//            'x_login' => $x_login,
//            'transaction_key' => $transaction_key,
//            'x_return_url' => $x_return_url
//        ]);
//    }
//    public function handlePaymentCallback(Request $request) {
//        $responseCode = $request->get('x_response_code');
//        $invoiceNumber = $request->get('x_invoice_num');
//
//        $invoice = Invoice::where('invoice_number', $invoiceNumber)
//            ->with('invoiceable')
//            ->whereIn('payment_status', [Invoice::PENDING, Invoice::FAILED])
//            ->first();
//
//        if (!$invoice) {
//            return view('vendor.backpack.base.payment-result', [
//                'success' => false,
//                'message' => 'Invoice not found or already processed.',
//                'invoice_number' => $invoiceNumber ?? null,
//            ]);
//        }
//
//        if ($responseCode == '1') {
//            // success
//            $invoice->update([
//                'payment_status' => Invoice::PAID,
//                'transaction_json' => json_encode($request->all()),
//                'paid_at' => now()
//            ]);
//
//            $invoiceable = $invoice->invoiceable;
//
//            if ($invoiceable instanceof Order) {
//                $invoiceable->update(['payment_status' => 'paid']);
//                Mail::to($invoiceable->email)->send(new OrderPlacedMail($invoiceable));
//            } elseif ($invoiceable instanceof RecurringOrder) {
//                $invoiceable->update(['recurring_payment_status' => 1]);
//                $invoiceable->load('order');
//                if ($invoiceable->order) {
//                    Mail::to($invoiceable->order->email)->send(new OrderPlacedMail($invoiceable, 'recurring'));
//                }
//            }
//
//            return view('vendor.backpack.base.payment-result', [
//                'success' => true,
//                'message' => 'Payment completed successfully!',
//                'invoice_number' => $invoice->invoice_number,
//                'amount' => $request->get('x_amount') ?? null,
//                'transaction_id' => $request->get('x_trans_id') ?? null,
//            ]);
//        } else {
//            // failed
//            $invoice->update([
//                'payment_status' => Invoice::FAILED,
//                'transaction_json' => json_encode($request->all()),
//                'paid_at' => now()
//            ]);
//
//            return view('vendor.backpack.base.payment-result', [
//                'success' => false,
//                'message' => 'Payment failed. Please try again.',
//                'invoice_number' => $invoice->invoice_number,
//            ]);
//        }
//    }


    public function view($id)
    {
        $this->crud->hasAccessOrFail('update'); // or 'view'

        $entry = \App\Models\ManualPayment::findOrFail($id);
        $orderNumbers = Order::all();
        $invoice = Invoice::where('id',$entry->invoice_id)->first();


        return view('vendor.backpack.crud.manualPayments-edit', [
            'entry' => $entry,
            'crud' => $this->crud,
            'invoice' => $invoice,
            'orderNumbers' => $orderNumbers,
        ]);
    }

    public function updateFromView($id)
    {
        $this->crud->hasAccessOrFail('update');
        $data = request()->all();

        $entry = \App\Models\ManualPayment::findOrFail($id);
        $entry->update($data);

        \Alert::success('Payment updated successfully.')->flash();
        return redirect()->route('payments.view', $id);
    }

    public function deleteFromView($id)
    {
        $this->crud->hasAccessOrFail('delete');

        \App\Models\ManualPayment::findOrFail($id)->delete();

        \Alert::success('Payment Deleted.')->flash();

        return redirect($this->crud->route);
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
    public function ajaxSearch_invoices(Request $request)
    {
        $search = $request->input('q');

        $invoices = Invoice::with('customer')
            ->where('status', '!=', Invoice::STATUS_DRAFT) // never expose unfinalized drafts
            ->whereIn('payment_status', [Invoice::PENDING, Invoice::FAILED])
            ->where('invoice_number', 'like', "%{$search}%")
            ->limit(20)
            ->get();

        $results = $invoices->map(function ($invoice) {
            // ---- Consolidated invoice (no polymorphic invoiceable) ----
            if (empty($invoice->invoiceable_type)) {
                $info = $this->resolveCustomerInfo($invoice);

                return [
                    'id'             => $invoice->id,
                    'order_id'       => null,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name'  => $info->customer_name ?? null,
                    'email'          => $info->email ?? null,
                    'total_cost'     => $invoice->total_amount ?? 0,
                    'type'           => 'consolidated',
                ];
            }

            // ---- Legacy direct order ----
            if ($invoice->invoiceable_type === Order::class) {
                $order = Order::where('invoice_id', $invoice->id)->first();

                return $order ? [
                    'id'             => $invoice->id,
                    'order_id'       => $order->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name'  => $order->customer_name,
                    'email'          => $order->email,
                    'total_cost'     => $order->total_cost ?? $invoice->total_amount ?? 0,
                    'type'           => 'direct',
                ] : null;
            }

            // ---- Legacy recurring order ----
            $recurring = RecurringOrder::where('id', $invoice->invoiceable_id)
                ->where('status', RecurringOrder::OPEN)
                ->whereNull('recurring_payment_status')
                ->with('order')
                ->first();

            return ($recurring && $recurring->order) ? [
                'id'             => $invoice->id,
                'order_id'       => $recurring->order->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_name'  => $recurring->order->customer_name,
                'email'          => $recurring->order->email,
                'total_cost'     => $recurring->order->total_cost ?? $invoice->total_amount ?? 0,
                'type'           => 'recurring',
            ] : null;
        })->filter()->values();


        return response()->json($results);
    }

}
