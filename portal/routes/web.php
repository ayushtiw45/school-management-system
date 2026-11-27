<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LicenseApiController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/lang/{locale}', [HomeController::class, 'locale'])->name('locale');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');

// "Install for Windows": sign up / log in first, then the download starts
Route::get('/install-for-windows', fn () => auth()->check() ? redirect()->route('account', ['start_download' => 1]) : redirect()->route('register', ['next' => 'download']))->name('install');

Route::get('/install/finish', [InstallController::class, 'finish'])->name('install.finish');
Route::post('/razorpay/webhook', [CheckoutController::class, 'webhook'])->name('razorpay.webhook');
Route::view('/refund-policy', 'refund')->name('refund');
Route::view('/contact', 'contact')->name('contact');

// Used by the desktop software to pick up its license automatically
Route::get('/api/license/{installationId}', [LicenseApiController::class, 'show'])->middleware('throttle:30,1')->name('api.license');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::get('/download', [AccountController::class, 'download'])->name('download');
    Route::put('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'password'])->name('account.password');
    // Opened from the License screen inside the software
    Route::get('/buy', fn () => redirect(route('account', ['installation_id' => request('installation_id')]).'#buy'))->name('buy');
    Route::post('/checkout/order', [CheckoutController::class, 'order'])->middleware('throttle:20,1')->name('checkout.order');
    Route::post('/checkout/verify', [CheckoutController::class, 'verify'])->name('checkout.verify');
    Route::get('/activate', [SubscriptionController::class, 'activate'])->name('activate');
    Route::post('/subscribe', [SubscriptionController::class, 'start'])->middleware('throttle:10,1')->name('subscribe');
    Route::post('/subscribe/verify', [SubscriptionController::class, 'verify'])->name('subscribe.verify');
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('/schools', [Admin\SchoolController::class, 'index'])->name('schools');
        Route::get('/schools/{user}', [Admin\SchoolController::class, 'show'])->name('schools.show');
        Route::get('/licenses', [Admin\LicenseController::class, 'index'])->name('licenses');
        Route::post('/licenses', [Admin\LicenseController::class, 'store'])->name('licenses.store');
        Route::get('/signing-key', [Admin\LicenseController::class, 'key'])->name('key');
        Route::get('/signing-key/backup', [Admin\LicenseController::class, 'backup'])->name('key.backup');
        Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments');
        Route::get('/subscriptions', [Admin\PaymentController::class, 'subscriptions'])->name('subscriptions');
        Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings');
        Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
    });
});
