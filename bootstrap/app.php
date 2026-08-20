<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureMaster;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(__DIR__.'/../routes/api/v1.php');
        },
    )
    ->withSchedule(function (Schedule $schedule) {
        // The auto-search radius is a function of elapsed time, so a skipped run
        // self-corrects on the next tick — overlapping runs would only duplicate work.
        $schedule->command('orders:expand-search-radius')
            ->everyMinute()
            ->withoutOverlapping();

        // Access is derived from subscription end dates, so a skipped run only
        // delays the state catching up — hourly is precise enough for day-long plans.
        $schedule->command('subscriptions:expire')
            ->hourly()
            ->withoutOverlapping();

        // The deadline is measured in hours, so an hourly sweep is precise enough
        // and a skipped run only delays the cancellation by one tick.
        $schedule->command('orders:cancel-stale-orders')
            ->hourly()
            ->withoutOverlapping();

        // GPS pings accrue at roughly 150k rows a day. Retention is measured in
        // days, so nightly is frequent enough, and a skipped run only leaves one
        // extra day of rows for the next one to take out.
        $schedule->command('locations:prune')
            ->dailyAt('03:30')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
        ]);

        // Resolve the request locale before authentication runs, otherwise errors
        // thrown by auth:sanctum (e.g. 401) would be rendered in the default locale
        // instead of the one requested via the X-Locale header.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: SetLocale::class,
        );

        $middleware->alias([
            'ensure.client' => EnsureClient::class,
            'ensure.master' => EnsureMaster::class,
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Single source of truth for API error responses: every exception on an
        // `api/*` route is rendered as clean, localized JSON. Web/Inertia routes
        // are left untouched (callback returns null → default Laravel handling).
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            // Expected business-rule violations carry their own localized message + status.
            if ($e instanceof ApiException) {
                return response()->json(['message' => $e->getMessage()], $e->statusCode());
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => __('api.unauthenticated')], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => __('api.forbidden')], 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => __('notifications.not_found')], 404);
            }

            if ($e instanceof NotFoundHttpException) {
                // An unmatched route ($request->route() === null) or a model-binding miss
                // gets a generic localized message — never leak the internal route path.
                // An intentional abort(404, '...') from a matched route keeps its message.
                $isGeneric = $request->route() === null
                    || $e->getPrevious() instanceof ModelNotFoundException;

                $message = $isGeneric
                    ? __('notifications.not_found')
                    : ($e->getMessage() ?: __('notifications.not_found'));

                return response()->json(['message' => $message], 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json(['message' => __('api.method_not_allowed')], 405);
            }

            if ($e instanceof ThrottleRequestsException) {
                return response()->json(['message' => __('api.too_many_requests')], 429);
            }

            // Any other HTTP exception (abort(403), etc.) — keep its status, fall back to a generic message.
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: __('api.server_error'),
                ], $e->getStatusCode());
            }

            // Truly unexpected — never leak internals to the client.
            $payload = ['message' => __('api.server_error')];

            if (config('app.debug')) {
                $payload['exception'] = $e->getMessage();
            }

            return response()->json($payload, 500);
        });
    })->create();
