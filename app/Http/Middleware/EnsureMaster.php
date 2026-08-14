<?php

namespace App\Http\Middleware;

use App\Models\Master;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMaster
{
    /** Route middleware parameter that keeps the gate open for a lapsed subscription. */
    private const ALLOW_EXPIRED = 'allow-expired';

    /**
     * @param  string|null  $mode  Pass `allow-expired` on endpoints a master must
     *                             still reach after their subscription ran out
     *                             (e.g. checking what to renew).
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if (! $user instanceof Master) {
            return response()->json(['message' => __('api.master.token_required')], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => __('api.master.disabled')], 403);
        }

        if ($mode !== self::ALLOW_EXPIRED && ! $user->hasActiveAccess()) {
            return response()->json(['message' => __('api.master.access_expired')], 403);
        }

        return $next($request);
    }
}
