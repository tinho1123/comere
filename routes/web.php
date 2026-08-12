<?php

use App\Http\Controllers\Admin\SelectCompanyController;
use App\Http\Controllers\Admin\TableQrController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Marketplace\ClientAddressController;
use App\Http\Controllers\Marketplace\ClientFavoriteController;
use App\Http\Controllers\Marketplace\CompanyRatingController;
use App\Http\Controllers\Marketplace\DeliveryTrackingController;
use App\Http\Controllers\Marketplace\DriverAuthController;
use App\Http\Controllers\Marketplace\DriverDashboardController;
use App\Http\Controllers\Marketplace\MarketplaceController;
use App\Http\Controllers\Marketplace\MarketplaceLoginController;
use App\Http\Controllers\Marketplace\SSOCallbackController;
use App\Http\Controllers\Marketplace\TableController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/table/{uuid}', [TableController::class, 'show'])->name('table.show');
Route::post('/table/{uuid}/open', [TableController::class, 'open'])->name('table.open');
Route::post('/table/{uuid}/name', [TableController::class, 'registerName'])->name('table.register-name');
Route::post('/table/{uuid}/item', [TableController::class, 'addItem'])->name('table.add-item');

Route::get('/entrega/{token}', [DeliveryTrackingController::class, 'show'])->name('delivery.tracking.show');
Route::post('/entrega/{token}/localizacao', [DeliveryTrackingController::class, 'updateLocation'])->name('delivery.tracking.update-location');

Route::middleware('guest:driver')->group(function () {
    Route::get('/motoboy/cadastro', [DriverAuthController::class, 'showRegister'])->name('motoboy.register.show');
    Route::post('/motoboy/cadastro', [DriverAuthController::class, 'register'])->name('motoboy.register');
    Route::get('/motoboy/login', [DriverAuthController::class, 'showLogin'])->name('motoboy.login.show');
    Route::post('/motoboy/login', [DriverAuthController::class, 'login'])->name('motoboy.login')->middleware('throttle:10,1');
});

Route::middleware('auth:driver')->group(function () {
    Route::post('/motoboy/logout', [DriverAuthController::class, 'logout'])->name('motoboy.logout');
    Route::get('/motoboy', [DriverDashboardController::class, 'show'])->name('motoboy.dashboard');
    Route::post('/motoboy/vinculos/{driverCompany}/aceitar', [DriverDashboardController::class, 'acceptInvite'])->name('motoboy.invite.accept');
    Route::post('/motoboy/vinculos/{driverCompany}/recusar', [DriverDashboardController::class, 'rejectInvite'])->name('motoboy.invite.reject');
    Route::get('/motoboy/poll', [DriverDashboardController::class, 'poll'])->name('motoboy.poll');
    Route::post('/motoboy/status', [DriverDashboardController::class, 'toggleStatus'])->name('motoboy.status.toggle');
});

Route::get('/', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/search', [MarketplaceController::class, 'search'])->name('marketplace.search');
Route::get('/store/{company:uuid}', [MarketplaceController::class, 'show'])->name('marketplace.show');

Route::post('/marketplace/login', [MarketplaceLoginController::class, 'login'])->name('marketplace.login')->middleware('throttle:10,1');
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
    Route::get('/meus-pedidos/{order:uuid}/rastreio', [MarketplaceController::class, 'trackOrder'])->name('marketplace.order.track');
    Route::post('/store/{company:uuid}/orders', [MarketplaceController::class, 'storeOrder'])->name('marketplace.order.store');
    Route::post('/store/{company:uuid}/orders/{order:uuid}/reorder', [MarketplaceController::class, 'reorder'])->name('marketplace.order.reorder');
    Route::post('/store/{company:uuid}/coupons/validate', [MarketplaceController::class, 'validateCoupon'])->name('marketplace.coupon.validate');
    Route::post('/favorites/{company:uuid}', [ClientFavoriteController::class, 'toggle'])->name('marketplace.favorite.toggle');
    Route::post('/store/{company:uuid}/rate', [CompanyRatingController::class, 'store'])->name('marketplace.rate');

    Route::get('/addresses', [ClientAddressController::class, 'index'])->name('client.addresses.index');
    Route::post('/addresses', [ClientAddressController::class, 'store'])->name('client.addresses.store');
    Route::put('/addresses/{clientAddress:uuid}', [ClientAddressController::class, 'update'])->name('client.addresses.update');
    Route::patch('/addresses/{clientAddress:uuid}/default', [ClientAddressController::class, 'setDefault'])->name('client.addresses.default');
    Route::delete('/addresses/{clientAddress:uuid}', [ClientAddressController::class, 'destroy'])->name('client.addresses.destroy');
});

// Push Notifications + Admin utilities (admin users only)
Route::middleware('auth')->group(function () {
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::get('/admin/table/{uuid}/qr-image', [TableQrController::class, 'image'])->name('table.qr-image');
    Route::get('/admin/select-company', [SelectCompanyController::class, 'show'])->name('admin.select-company');
    Route::post('/admin/select-company', [SelectCompanyController::class, 'store'])->name('admin.select-company.store');
});

// Rotas do Admin (existente)
Route::get('/login', [LoginController::class, 'index'])->middleware('guest');
Route::post('/login', [LoginController::class, 'authenticate'])->middleware(['guest', 'throttle:10,1']);
Route::post('/logout', [LogoutController::class, 'logout'])->middleware('auth');
