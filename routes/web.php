<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Marketplace\ClientAddressController;
use App\Http\Controllers\Marketplace\MarketplaceController;
use App\Http\Controllers\Marketplace\MarketplaceLoginController;
use App\Http\Controllers\Marketplace\SSOCallbackController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/store/{company:uuid}', [MarketplaceController::class, 'show'])->name('marketplace.show');

Route::post('/marketplace/login', [MarketplaceLoginController::class, 'login'])->name('marketplace.login');
Route::post('/marketplace/logout', [MarketplaceLoginController::class, 'logout'])->name('marketplace.logout');

// Fluxo SSO e Cadastro Completo
Route::get('/sso-callback', function () {
    return redirect()->route('marketplace.index');
});
Route::post('/sso-callback', SSOCallbackController::class)->name('marketplace.sso-callback');
Route::get('/complete-profile', [SSOCallbackController::class, 'completeProfile'])->name('marketplace.complete-profile');
Route::post('/complete-profile', [SSOCallbackController::class, 'storeProfile'])->name('marketplace.store-profile');

Route::middleware('auth:client')->group(function () {
    Route::get('/meus-pedidos', [MarketplaceController::class, 'orders'])->name('marketplace.orders');
    Route::post('/store/{company:uuid}/orders', [MarketplaceController::class, 'storeOrder'])->name('marketplace.order.store');

    Route::get('/addresses', [ClientAddressController::class, 'index'])->name('client.addresses.index');
    Route::post('/addresses', [ClientAddressController::class, 'store'])->name('client.addresses.store');
    Route::put('/addresses/{clientAddress:uuid}', [ClientAddressController::class, 'update'])->name('client.addresses.update');
    Route::patch('/addresses/{clientAddress:uuid}/default', [ClientAddressController::class, 'setDefault'])->name('client.addresses.default');
    Route::delete('/addresses/{clientAddress:uuid}', [ClientAddressController::class, 'destroy'])->name('client.addresses.destroy');
});

// Push Notifications (admin users only)
Route::middleware('auth')->group(function () {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
});

// Rotas do Admin (existente)
Route::get('/login', [LoginController::class, 'index'])->middleware('guest');
Route::post('/login', [LoginController::class, 'authenticate'])->middleware('guest');
Route::post('/logout', [LogoutController::class, 'logout'])->middleware('auth');
