<?php

use App\Http\Controllers\ProfileController;
use App\Http\Middleware\RedirectIfAuthenticatedCustom;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryPageController;
use App\Http\Controllers\Admin\OrderCrudController;
use App\Http\Controllers\Admin\ProductCrudController;
use App\Http\Controllers\Admin\WarehouseSaleCrudController;
use App\Http\Controllers\Admin\ManualPaymentCrudController;
use App\Http\Controllers\Website\LoginController;
use App\Http\Controllers\Website\CustomerRegisterController;
use App\Http\Controllers\Website\CustomerForgotPasswordController;

use App\Mail\OrderPlacedMail;
use Illuminate\Support\Facades\Mail;
use App\Models\Order;


// routes/web.php
Route::get('/route-cache', function() {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    return redirect()->route('home')->with('cache','System Cache Has Been Removed.');
});


Route::middleware(['web', App\Http\Middleware\Localization::class])->group(function () {
    Route::get('/', [App\Http\Controllers\WebsiteController::class, 'index'])->name('home');
    Route::get('/contact', [App\Http\Controllers\WebsiteController::class, 'contact'])->name('contact');
    Route::get('/order', [App\Http\Controllers\WebsiteController::class, 'showOrderForm'])->name('order');
    Route::post('/order', [App\Http\Controllers\WebsiteController::class, 'storeOrder'])->name('submitOrder');
    Route::get('/location', [App\Http\Controllers\WebsiteController::class, 'location'])->name('location');
    Route::post('/location', [App\Http\Controllers\WebsiteController::class, 'storeLocation'])->name('storeLocation');
    Route::get('/review', [App\Http\Controllers\WebsiteController::class, 'review'])->name('review');
});



Route::get('/dryice_uses', function () {
    return view('website.dryice.dryice_uses');
});
Route::get('/dryice_safety', function () {
    return view('website.dryice.safety');
});
Route::get('/blasting_info', function () {
    return view('website.blasting.blasting_info');
});
Route::get('/blasting_examples', function () {
    return view('website.blasting.blasting_example');
});
Route::get('/blasting_manuals', function () {
    return view('website.blasting.blasting_manuals');
});
Route::get('/blasting_services', function () {
    return view('website.blasting.blasting_services');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
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

    // Users
    Route::crud('users', 'UserCrudController');
    Route::crud('roles', 'RoleCrudController');
    Route::crud('permissions', 'PermissionCrudController');

    // Orders
    Route::crud('orders', 'OrderCrudController');

    // Lists
    Route::crud('postal-codes', 'PostalCodeCrudController');
    Route::crud('one-off-orders', 'OneOffOrdersCrudController');
    Route::crud('ice-orders', 'IceOrdersCrudController');
    Route::crud('variables', 'VariablesCrudController');
    Route::crud('customers', 'CustomersCrudController');
    Route::crud('log-files', 'LogFilesCrudController');

    // Reports
    Route::get('/admin/inventory', [InventoryPageController::class, 'index'])->name('admin.inventory');
    Route::crud('inventory', 'InventoryCrudController');
    Route::get('/admin/warehouse-sales', [WarehouseSaleCrudController::class, 'index'])->name('admin.warehouse_sales');
    Route::crud('warehouse-sales', 'WarehouseSaleCrudController');

    Route::get('admin/ajax/orders', [ManualPaymentCrudController::class, 'ajaxSearch'])->name('orders.ajax-search');
    Route::get('admin/ajax/invoices', [ManualPaymentCrudController::class, 'ajaxSearch_invoices'])->name('invoices.ajax-search');
    Route::get('admin/ajax/customers', [App\Http\Controllers\Admin\OrderCrudController::class, 'ajaxCustomers'])->name('ajax.customers');



});
// Login
//
//Route::get('/login', [LoginController::class, 'showLoginForm'])
//    ->middleware([RedirectIfAuthenticatedCustom::class, 'handle'])
//    ->name('login.form');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login.form');

Route::post('/login', [LoginController::class, 'login'])->name('login.custom');

// Logout
Route::POST('/logout', [LoginController::class, 'logout'])->name('logout.custom');





Route::get('/register-customer', [CustomerRegisterController::class, 'showForm'])->name('customer.register.form');
Route::post('/register-customer', [CustomerRegisterController::class, 'register'])->name('customer.register');

Route::get('/api/products', [ProductCrudController::class, 'getAllProducts']);

use App\Http\Controllers\SupplierController;


Route::get('/test-closest-supplier', [SupplierController::class, 'checkClosestSupplier'])->middleware('throttle:checkout-quote');


Route::post('/get-delivery-quote', [SupplierController::class, 'getDeliveryQuote'])->middleware('throttle:checkout-quote');

Route::post('/get-novex-quote', [SupplierController::class, 'getNovexQuote']);

// Creates a real (billed) Novex delivery, so admins only; same guard as the admin pages that call it.
Route::post('/orders/{id}/push-novex', [SupplierController::class, 'pushToNovex'])->middleware('admin');




Route::prefix('customer')->group(function () {
    Route::get('forgot-password', [CustomerForgotPasswordController::class, 'showLinkRequestForm'])->name('customer.password.request');
    Route::post('forgot-password', [CustomerForgotPasswordController::class, 'sendResetLinkEmail'])->name('customer.password.email');

    Route::get('reset-password/{token}', [CustomerForgotPasswordController::class, 'showResetForm'])->name('customer.password.reset');
    Route::post('reset-password', [CustomerForgotPasswordController::class, 'reset'])->name('customer.password.update');

    Route::get('password-reset-success', function () {
        return view('website.auth.customer-password-success');
    })->name('customer.password.reset.success');
});
Route::get('/change-language/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'fr'])) {
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }
//    dd(app()->get('locale'),$locale);
    return redirect()->back();
})->name('change.language');

// require __DIR__ . '/auth.php';
