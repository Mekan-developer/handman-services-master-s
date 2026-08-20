<?php

use App\Http\Controllers\Api\V1\Client\ClientAuthController;
use App\Http\Controllers\Api\V1\Client\ClientCatalogController;
use App\Http\Controllers\Api\V1\Client\ClientOrderController;
use App\Http\Controllers\Api\V1\Client\ClientOrderTrackingController;
use App\Http\Controllers\Api\V1\Client\ClientProfileController;
use App\Http\Controllers\Api\V1\Client\ClientSettingController;
use App\Http\Controllers\Api\V1\Client\MasterApplicationController;
use App\Http\Controllers\Api\V1\MasterAvailabilityController;
use App\Http\Controllers\Api\V1\MasterLocationController;
use App\Http\Controllers\Api\V1\MasterOrderController;
use App\Http\Controllers\Api\V1\MasterProfileController;
use App\Http\Controllers\Api\V1\MasterSubscriptionController;
use App\Http\Controllers\Api\V1\MasterTaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
 * Mobile API v1 — one Flutter app serving both roles.
 *
 * Everyone signs up as a client: OTP → Sanctum personal access token (Bearer),
 * token name `mobile-client`, guarded by `ensure.client`. "Become a master" adds
 * a master profile on top of that same account; once an administrator approves
 * it and issues a subscription, the very same token also opens the `master/*`
 * endpoints, guarded by `ensure.master`.
 */

/*
 * Broadcast channel auth for mobile apps.
 * Flutter clients set authEndpoint to /api/v1/broadcasting/auth and pass their
 * Sanctum Bearer token. The guard config (web + sanctum) allows Broadcast::auth()
 * to resolve both session-based admin users and token-based Master/Client models.
 */
Route::post('broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware('auth:sanctum')->name('api.v1.broadcasting.auth');

Route::prefix('master')->group(function () {

    // Public — subscription price list. A master with a lapsed subscription cannot
    // authenticate at all, so this has to stay reachable without a token.
    Route::get('subscription-plans', [MasterSubscriptionController::class, 'plans'])->name('api.v1.master.subscription-plans');

    // Own subscription — readable even once access has run out, so the app can
    // show "expired on …, renew" instead of a bare 403.
    Route::get('subscription', [MasterSubscriptionController::class, 'current'])
        ->middleware(['auth:sanctum', 'ensure.master:allow-expired'])
        ->name('api.v1.master.subscription');

    // Protected — client token whose account carries an approved master profile
    Route::middleware(['auth:sanctum', 'ensure.master'])->group(function () {

        Route::get('me', [MasterProfileController::class, 'show'])->name('api.v1.master.me');
        Route::patch('me', [MasterProfileController::class, 'update'])->name('api.v1.master.me.update');
        Route::patch('availability', [MasterAvailabilityController::class, 'update'])->name('api.v1.master.availability.update');

        // The {master} segment is kept for URL compatibility with the mobile apps,
        // but the id must match the authenticated master — see the controller.
        Route::post('{master}/location', [MasterLocationController::class, 'store'])->name('api.v1.master.location.store');

        Route::prefix('orders')->name('api.v1.master.orders.')->group(function () {
            Route::get('/', [MasterOrderController::class, 'index'])->name('index');

            // Auto-search feed. Must stay above the {order} route, otherwise
            // "available" is swallowed as an order id.
            Route::get('available', [MasterOrderController::class, 'available'])->name('available');
            Route::post('{order}/respond', [MasterOrderController::class, 'respond'])->name('respond');
            Route::post('{order}/decline', [MasterOrderController::class, 'decline'])->name('decline');

            Route::get('{order}', [MasterOrderController::class, 'show'])
                ->name('show');
            Route::post('{order}/start', [MasterOrderController::class, 'start'])->name('start');
            Route::post('{order}/complete', [MasterOrderController::class, 'complete'])->name('complete');

            Route::prefix('{order}/tasks')->name('tasks.')->group(function () {
                Route::post('/', [MasterTaskController::class, 'store'])->name('store');
                Route::post('{task}/photo', [MasterTaskController::class, 'uploadPhoto'])->name('photo');
                Route::delete('{task}', [MasterTaskController::class, 'destroy'])->name('destroy');
            });
        });
    });
});

Route::prefix('client')->group(function () {

    // Public — app settings (rules/terms shown before registration)
    Route::get('settings', [ClientSettingController::class, 'show'])->name('api.v1.client.settings');

    // Public catalog
    Route::get('oblasts', [ClientCatalogController::class, 'oblasts'])->name('api.v1.client.oblasts');
    Route::get('regions', [ClientCatalogController::class, 'regions'])->name('api.v1.client.regions');
    Route::get('cities', [ClientCatalogController::class, 'cities'])->name('api.v1.client.cities');
    Route::get('categories', [ClientCatalogController::class, 'categories'])->name('api.v1.client.categories');
    Route::get('categories/search', [ClientCatalogController::class, 'searchCategories'])->name('api.v1.client.categories.search');
    Route::get('categories/{category}/content', [ClientCatalogController::class, 'categoryContent'])->name('api.v1.client.categories.content');
    Route::get('banners', [ClientCatalogController::class, 'banners'])->name('api.v1.client.banners');

    // Auth
    Route::prefix('auth')->name('api.v1.client.auth.')->group(function () {
        Route::post('request-otp', [ClientAuthController::class, 'requestOtp'])->name('request-otp');
        Route::post('verify-otp', [ClientAuthController::class, 'verifyOtp'])->name('verify-otp');

        // Require the token issued by verify-otp.
        Route::middleware(['auth:sanctum', 'ensure.client'])->group(function () {
            Route::post('complete-registration', [ClientAuthController::class, 'completeRegistration'])->name('complete-registration');
            Route::post('logout', [ClientAuthController::class, 'logout'])->name('logout');
        });
    });

    // Protected — requires Sanctum token + client tokenable
    Route::middleware(['auth:sanctum', 'ensure.client'])->group(function () {

        Route::get('me', [ClientProfileController::class, 'show'])->name('api.v1.client.me');
        Route::patch('me', [ClientProfileController::class, 'update'])->name('api.v1.client.me.update');

        // "Become a master" — submit the application and follow its review.
        Route::get('master-application', [MasterApplicationController::class, 'show'])->name('api.v1.client.master-application.show');
        Route::post('master-application', [MasterApplicationController::class, 'store'])->name('api.v1.client.master-application.store');

        Route::prefix('orders')->name('api.v1.client.orders.')->group(function () {
            Route::get('/', [ClientOrderController::class, 'index'])->name('index');
            Route::get('{order}', [ClientOrderController::class, 'show'])->name('show');
            Route::post('/', [ClientOrderController::class, 'store'])->name('store');
            Route::patch('{order}', [ClientOrderController::class, 'update'])->name('update');
            Route::post('{order}/cancel', [ClientOrderController::class, 'cancel'])->name('cancel');
            Route::post('{order}/review', [ClientOrderController::class, 'storeReview'])->name('review');

            // Live trail of the assigned master. Answers `is_active: false`
            // once the order is finished, so the app stops following.
            Route::get('{order}/track', [ClientOrderTrackingController::class, 'show'])->name('track');

            Route::get('{order}/responses', [ClientOrderController::class, 'responses'])->name('responses');
            Route::post('{order}/responses/{responseId}/approve', [ClientOrderController::class, 'approveResponse'])->name('responses.approve');
            Route::post('{order}/responses/{responseId}/reject', [ClientOrderController::class, 'rejectResponse'])->name('responses.reject');
        });
    });
});
