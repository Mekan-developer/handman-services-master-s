<?php

use App\Http\Controllers\BannerController;
use App\Http\Controllers\CategoryContentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterApplicationController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\MasterSubscriptionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OblastController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PendingOtpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\TilesController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/tiles/{z}/{x}/{y}.pbf', [TilesController::class, 'vectorTile'])
    ->where(['z' => '\d{1,2}', 'x' => '\d+', 'y' => '\d+'])
    ->name('tiles.vector');

Route::view('/map', 'map');

Route::post('/locale/{locale}', function (string $locale) {
    $supported = ['ru', 'tk'];
    if (in_array($locale, $supported, strict: true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.set');

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/system-status', SystemStatusController::class)->name('system.status');

    // Profile — accessible to all authenticated users (all roles)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard & all other admin sections — administrator and manager only
    Route::middleware('role:administrator,manager')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('verified')
            ->name('dashboard');

        Route::get('pending-otps', [PendingOtpController::class, 'index'])->name('pending-otps.index');
        Route::get('pending-otps/data', [PendingOtpController::class, 'data'])->name('pending-otps.data');
        Route::delete('pending-otps/{id}', [PendingOtpController::class, 'destroy'])->name('pending-otps.destroy');

        Route::resource('oblasts', OblastController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('regions', RegionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('cities', CityController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('categories/{category}/content', [CategoryContentController::class, 'upsert'])->name('categories.content.upsert');
        Route::get('masters/map', [MasterController::class, 'map'])->name('masters.map');
        Route::get('masters/{master}/trajectory', [MasterController::class, 'trajectory'])->name('masters.trajectory');
        Route::resource('masters', MasterController::class)->only(['index', 'destroy']);
        Route::post('masters/{master}', [MasterController::class, 'update'])->name('masters.update');

        // "Become a master" applications submitted from the mobile app.
        Route::get('master-applications', [MasterApplicationController::class, 'index'])->name('master-applications.index');
        Route::post('master-applications/{master}/approve', [MasterApplicationController::class, 'approve'])->name('master-applications.approve');
        Route::post('master-applications/{master}/reject', [MasterApplicationController::class, 'reject'])->name('master-applications.reject');

        Route::resource('banners', BannerController::class)->only(['index', 'store', 'destroy']);
        Route::post('banners/{banner}', [BannerController::class, 'update'])->name('banners.update');
        Route::post('banners/{banner}/toggle', [BannerController::class, 'toggle'])->name('banners.toggle');

        Route::post('clients/{client}/toggle-block', [ClientController::class, 'toggleBlock'])->name('clients.toggle-block');
        Route::resource('clients', ClientController::class)->only(['index', 'store', 'destroy']);

        // POST rather than PUT: the form carries the avatar, and PHP only fills
        // $_FILES on POST. Same reason as masters and banners above.
        Route::post('clients/{client}', [ClientController::class, 'update'])->name('clients.update');

        Route::resource('orders', OrderController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::post('orders/{order}/restart-search', [OrderController::class, 'restartSearch'])->name('orders.restart-search');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::get('orders/{order}/master-trajectory', [OrderController::class, 'masterTrajectoryForOrder'])->name('orders.master-trajectory');

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::post('{id}/read', [NotificationController::class, 'markRead'])->name('read');
            Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::delete('{id}', [NotificationController::class, 'destroy'])->name('destroy');
            Route::delete('/', [NotificationController::class, 'destroyAll'])->name('destroy-all');
        });

        // Administrators only — managers have no access to these sections
        Route::middleware('role:administrator')->group(function () {
            Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

            // One page holds both halves: the owner's plans and the masters' purchases.
            Route::get('subscriptions', [MasterSubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::post('masters/{master}/subscriptions', [MasterSubscriptionController::class, 'store'])->name('masters.subscriptions.store');
            Route::put('subscriptions/{subscription}', [MasterSubscriptionController::class, 'update'])->name('subscriptions.update');
            Route::post('subscriptions/{subscription}/status', [MasterSubscriptionController::class, 'updateStatus'])->name('subscriptions.update-status');
            Route::delete('subscriptions/{subscription}', [MasterSubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

            Route::resource('subscription-plans', SubscriptionPlanController::class)->only(['store', 'update', 'destroy']);
            Route::post('subscription-plans/{subscriptionPlan}/toggle', [SubscriptionPlanController::class, 'toggle'])->name('subscription-plans.toggle');
        });

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});

require __DIR__.'/auth.php';
