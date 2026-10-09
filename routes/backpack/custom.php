<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\OrderCrudController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IceOrdersCrudController;
use App\Http\Controllers\Admin\ProductCrudController;
use App\Http\Controllers\Admin\VariablesCrudController;
use App\Http\Controllers\Admin\CustomersCrudController;
use App\Http\Controllers\Admin\ManualPaymentCrudController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Customer\CustomerDashboardController;
use App\Http\Controllers\Customer\CustomerProfileController;
use App\Http\Controllers\InvoiceGeneratorController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('admin/reports', [ReportsController::class, 'index'])
    ->middleware(['web', 'admin']) // important!
    ->name('reports');

// Called by the admin Emails page. They return customer details and send mail, so admins only
// ('web' is needed too: this file's top-level routes get no session without it).
Route::post('/admin/orders_of_date', [OrderCrudController::class, 'getOrdersByLocationAndDate'])->middleware(['web', 'admin'])->name('orders.byDate');
Route::post('/admin/send-email', [OrderCrudController::class, 'sendDryIceOrderEmail'])->middleware(['web', 'admin'])->name('send.ordersEmail');


use App\Http\Middleware\CustomerMiddleware;

Route::group([
    'middleware' => ['web', CustomerMiddleware::class],
    'prefix' => 'customer',
], function () {
    Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('customer.dashboard');
    Route::get('/my_orders', [CustomerDashboardController::class, 'myOrders'])->name('customer.orders');
    Route::get('/order-details/{id}', [CustomerDashboardController::class, 'orderDetails'])->name('customer.order-details');
    Route::get('/profile', [CustomerProfileController::class, 'show'])->name('customer.profile');
    Route::put('/profile', [CustomerProfileController::class, 'updateProfile'])->name('customer.profile.update');
    Route::put('/change-password', [CustomerProfileController::class, 'updatePassword'])->name('customer.password.change');
    Route::post('/recurring-orders/{recurringOrder}/cancel', [\App\Http\Controllers\RecurringOrderController::class, 'cancelRecurringOrder'])->name('recurring-orders.cancel');
    Route::get('/invoice/{id}', [CustomerDashboardController::class, 'orderInvoice'])->name('customer.invoice');
    Route::get('/consolidated-invoice/{invoice}', [CustomerDashboardController::class, 'consolidatedInvoice'])->name('customer.consolidated-invoice');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () {
    Route::crud('users', 'UserCrudController');
    Route::crud('roles', 'RoleCrudController');
    Route::crud('permissions', 'PermissionCrudController');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Orders
    Route::crud('orders', 'OrderCrudController');

    // Lists
    Route::crud('postal-codes', 'PostalCodeCrudController');
    Route::crud('one-off-orders', 'OneOffOrdersCrudController');
    Route::crud('ice-orders', 'IceOrdersCrudController');
    Route::crud('product', 'ProductCrudController');
    Route::crud('variables', 'VariablesCrudController');
    Route::crud('customers', 'CustomersCrudController');
    Route::crud('log-files', 'LogFilesCrudController');


    //custom edit, delete and update routes for ice orders
    Route::get('ice-orders/{id}/view', [IceOrdersCrudController::class, 'view'])->name('ice-orders.view');
    Route::post('ice-orders/{id}/update', [IceOrdersCrudController::class, 'updateFromView'])->name('ice-orders.update');
    Route::delete('ice-orders/{id}/delete', [IceOrdersCrudController::class, 'deleteFromView'])->name('ice-orders.custom_delete');

    //custom edit, delete and update routes for Products
    Route::get('product/{id}/view', [ProductCrudController::class, 'view'])->name('products.view');
    Route::post('product/{id}/update', [ProductCrudController::class, 'updateFromView'])->name('products.update');
    Route::delete('product/{id}/delete', [ProductCrudController::class, 'deleteFromView'])->name('product.custom_delete');

    //custom edit, delete and update routes for Variables
    Route::get('variables/{id}/view', [VariablesCrudController::class, 'view'])->name('variable.view');
    Route::post('variables/{id}/update', [VariablesCrudController::class, 'updateFromView'])->name('variable.update');
    Route::delete('variables/{id}/delete', [VariablesCrudController::class, 'deleteFromView'])->name('variable.custom_delete');

    //custom edit, delete and update routes for Customers
    Route::get('customers/{id}/view', [CustomersCrudController::class, 'view'])->name('customer.view');
    Route::post('customers/{id}/update', [CustomersCrudController::class, 'updateFromView'])->name('customer.update');
    Route::delete('customers/{id}/delete', [CustomersCrudController::class, 'deleteFromView'])->name('customer.custom_delete');

    //custom edit, delete and update routes for Customers
    Route::get('manual-payments/{id}/view', [ManualPaymentCrudController::class, 'view'])->name('payments.view');
    Route::post('manual-payments/{id}/update', [ManualPaymentCrudController::class, 'updateFromView'])->name('payments.update');
    Route::delete('manual-payments/{id}/delete', [ManualPaymentCrudController::class, 'deleteFromView'])->name('payments.custom_delete');

    // Reports
    Route::crud('inventory', 'InventoryCrudController');
    Route::get('inventory', function () {
        return view('vendor.backpack.base.inventory');
    });

    Route::crud('warehouse-sales', 'WarehouseSaleCrudController');
    Route::get('warehouse-sales', function () {
        return view('vendor.backpack.base.warehouse_sales');
    });

    //Others
    Route::crud('manual-payments', 'ManualPaymentCrudController');

    Route::post('admin/manual-payments', [\App\Http\Controllers\Admin\ManualPaymentCrudController::class, 'store'])
        ->middleware(['web', 'admin']) // important!
        ->name('manual-payments.store');

    Route::post('manual-payments/initiate', [ManualPaymentCrudController::class, 'initiatePayment'])->name('manual-payments.initiate');
    Route::get('manual-payments/callback', [ManualPaymentCrudController::class, 'handlePaymentCallback'])->name('manual-payments.callback');




    Route::crud('emails', 'EmailCrudController');
    Route::get('emails', function () {
        return view('vendor.backpack.base.emails');
    })->name('emails');



    Route::crud('product', 'ProductCrudController');


    // Invoice routes
    Route::get('/invoice/{invoice}/view', [OrderCrudController::class, 'viewInvoice'])->name('invoice.view');
    Route::get('/invoice/{invoice}/download', [OrderCrudController::class, 'downloadInvoice'])->name('invoice.download');


    Route::prefix('invoice-generator')->group(function () {
        Route::get('/', [InvoiceGeneratorController::class, 'index'])->name('admin.invoice-generator.index');
        Route::post('/draft', [InvoiceGeneratorController::class, 'buildDraft'])->name('admin.invoice-generator.draft');
        Route::post('/draft/from-order', [InvoiceGeneratorController::class, 'buildDraftFromOrder'])->name('admin.invoice-generator.draft.from-order');
        Route::post('/draft/update', [InvoiceGeneratorController::class, 'updateDraft'])->name('admin.invoice-generator.draft.update');
        Route::post('/draft/discard', [InvoiceGeneratorController::class, 'discardDraft'])->name('admin.invoice-generator.draft.discard');
        Route::get('/draft/{invoice}/resume', [InvoiceGeneratorController::class, 'resumeDraft'])->name('admin.invoice-generator.draft.resume');
        Route::post('/finalize', [InvoiceGeneratorController::class, 'finalize'])->name('admin.invoice-generator.finalize');
        Route::get('/{invoice}/pdf', [InvoiceGeneratorController::class, 'downloadPdf'])->name('admin.invoice-generator.pdf');
        Route::get('/invoice/{invoice}/view', [InvoiceGeneratorController::class, 'viewInvoice'])->name('consolidated.invoice.view');
        Route::post('/invoice/{invoice}/email', [InvoiceGeneratorController::class, 'sendInvoiceEmail'])->name('consolidated.invoice.email');
        Route::get('/invoice/{invoice}/email-history', [InvoiceGeneratorController::class, 'emailHistory'])->name('consolidated.invoice.email-history');
    });

});

Route::post('/order/ajax-create-from-review', [OrderCrudController::class, 'ajaxCreateFromReview'])->middleware('throttle:checkout-order')->name('orders.ajax-create-from-review');

Route::get('/payment-redirect/{order}', [OrderCrudController::class, 'paymentRedirect'])->name('payment.redirect');

Route::get('/orders/payment-details', [OrderCrudController::class, 'getOrderDetails'])->name('orders.get-payment-details');

Route::get('payments/initiate', [OrderCrudController::class, 'initiatePayment'])->name('payments.initiate');
Route::get('payments/callback', [OrderCrudController::class, 'handlePaymentCallback'])->middleware('throttle:payment-callback')->name('payments.callback');

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () {

    Route::get('customers/{customer}/addresses', [OrderCrudController::class, 'getCustomerAddresses']);
    Route::post('customers/{customer}/addresses', [OrderCrudController::class, 'store']);

    Route::get('customers/{customer}/addresses',
        [CustomersCrudController::class, 'getCustomerAddresses']);

    Route::post('customers/{customer}/addresses',
        [CustomersCrudController::class, 'addAddress']);

    Route::put('customers/{customer}/addresses/{address}',
        [CustomersCrudController::class, 'updateAddress']);

    Route::delete('customers/{customer}/addresses/{address}',
        [CustomersCrudController::class, 'deleteAddress']);

    Route::post('customers/{customer}/addresses/{address}/set-default',
        [CustomersCrudController::class, 'setDefaultAddress']);



    Route::get('customers-pricing',[CustomersCrudController::class, 'customerPricing']);
    Route::post('/customer-pricing/{customer}',[CustomersCrudController::class, 'customerPricingUpdate'])->name('customer-pricing.update');


    Route::post('orders/ajax-create', [OrderCrudController::class, 'ajaxCreate']);

    Route::put('orders/{id}/ajax-update', [OrderCrudController::class, 'updateOrderAjax'])
        ->name('admin.orders.ajax-update');

    Route::delete('order/{id}/delete/', [OrderCrudController::class, 'deleteOrderAjax'])
        ->name('admin.orders.ajax-delete');


    Route::get('orders/modal/{id}/edit', [OrderCrudController::class, 'editModal'])->name('admin.orders.modal.edit');
    Route::get('orders/modal/create', [OrderCrudController::class, 'modalCreate'])->name('admin.orders.modal.create');

    Route::get('orders/manual/modal/create',[OrderCrudController::class, 'manualCreateModal'])->name('admin.orders.manual.modal.create');
    Route::get('orders/manual/modal/{id}/edit',[OrderCrudController::class, 'manualEditModal'])->name('admin.orders.manual.modal.edit');

    Route::get('/customers/search', [CustomersCrudController::class, 'searchCustomers'])
        ->name('admin.customers.search');

    Route::get('/customers/get-by-email', [OrderCrudController::class, 'getCustomerByEmail'])
        ->name('admin.customers.get-by-email');




});


