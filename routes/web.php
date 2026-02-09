<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SubscriptionController;


// Paystack callback route (must be in web.php for redirects)
Route::get('/subscriptions/payment/callback', [SubscriptionController::class, 'paymentCallback'])
    ->name('subscription.callback');

// Success and failure pages (optional)
Route::get('/subscription/success', function () {
    return view('subscription.success'); // You can create this view later
})->name('subscription.success');

Route::get('/subscription/failed', function () {
    return view('subscription.failed'); // You can create this view later
})->name('subscription.failed');

Route::get('/', function () {
    return view('welcome');
});
