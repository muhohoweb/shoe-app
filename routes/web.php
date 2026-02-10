<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CategoryController;

Route::get('/', [ShopController::class, 'index'])->name('home');


Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Categories Routes
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');


// Products Routes
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');


    Route::resource('orders', OrderController::class)->only(['index', 'update', 'destroy']);

    // M-Pesa Settings & Balance
    Route::get('/settings/mpesa', [MpesaController::class, 'settings'])->name('mpesa.settings');
    Route::post('/settings/mpesa/balance', [MpesaController::class, 'queryBalance'])->name('mpesa.balance');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');


    Route::apiResource('jobs', SchedulesController::class);
    Route::post('/jobs/{id}/toggle', [SchedulesController::class, 'toggleStatus'])->name('jobs.toggle');

});
//WhatsApp callbacks
Route::match(['get', 'post'], '/whatsapp/webhook', [WhatsAppController::class, 'webhook']);

Route::post('/whatsapp/send-dispatch', [WhatsAppController::class, 'sendDispatchNotification']);

// Public Shop Routes (no auth)
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::post('/shop/order', [ShopController::class, 'placeOrder'])->name('shop.order');


// M-Pesa Callbacks (no auth, no CSRF)
Route::post('/mpesa/callback', [MpesaController::class, 'callback']);
Route::post('/mpesa/balance/callback', [MpesaController::class, 'balanceCallback']);
Route::get('/mpesa/status/{identifier}', [MpesaController::class, 'checkStatus']);
Route::get('/api/mpesa/balance', [MpesaController::class, 'getBalance']);


require __DIR__ . '/settings.php';
