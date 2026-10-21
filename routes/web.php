<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderActionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\QueueController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', 'throttle:web'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/catalog', CatalogController::class)
        ->middleware('can:product.view')
        ->name('catalog');

    Route::get('/queue', QueueController::class)->name('queue');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware(['throttle:actions', 'can:po.create'])
        ->name('orders.store');

    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->whereUuid('order')
        ->name('orders.show');

    Route::get('/orders/{order}/invoice', InvoiceController::class)
        ->whereUuid('order')
        ->name('orders.invoice');

    // Nama method di OrderActionController sama dengan nama aksinya.
    foreach (['submit', 'cancel', 'approve', 'reject', 'pack', 'ship', 'complete'] as $action) {
        Route::post("/orders/{order}/{$action}", [OrderActionController::class, $action])
            ->middleware('throttle:actions')
            ->whereUuid('order')
            ->name("orders.{$action}");
    }
});
