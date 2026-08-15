<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\Master;
use App\Repositories\MasterRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the master half of the mobile app.
 *
 * Authentication is always a client token — a master profile is an add-on the
 * client applied for. This middleware resolves that profile, checks it is
 * allowed to work, and then swaps the request's user for the Master model so
 * every downstream master controller keeps reading `$request->user()` as before.
 *
 * Note the swap does not touch authentication: `auth:sanctum` has already run
 * and the token still belongs to the Client. Anything needing the token itself
 * (logout, for instance) lives on the client side of the API.
 */
class EnsureMaster
{
    /** Route middleware parameter that keeps the gate open for a lapsed subscription. */
    private const ALLOW_EXPIRED = 'allow-expired';

    public function __construct(private readonly MasterRepository $masters) {}

    /**
     * @param  string|null  $mode  Pass `allow-expired` on endpoints a master must
     *                             still reach after their subscription ran out
     *                             (e.g. checking what to renew).
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $client = $request->user();

        if (! $client instanceof Client) {
            return $this->deny('api.master.token_required', 'token_required');
        }

        $master = $this->masters->findByClient($client);

        if (! $master instanceof Master) {
            return $this->deny('api.master.not_a_master', 'not_a_master');
        }

        if ($master->isPending()) {
            return $this->deny('api.master.application_pending', 'application_pending');
        }

        if (! $master->isApproved()) {
            return $this->deny('api.master.application_rejected', 'application_rejected');
        }

        if (! $master->is_active) {
            return $this->deny('api.master.disabled', 'disabled');
        }

        if ($mode !== self::ALLOW_EXPIRED && ! $master->hasActiveAccess()) {
            return $this->deny('api.master.access_expired', 'access_expired');
        }

        $request->setUserResolver(fn () => $master);

        return $next($request);
    }

    /**
     * The `reason` code lets the app route the user to the right screen —
     * "finish your application", "wait for review", "renew your subscription" —
     * instead of guessing from a translated string.
     */
    private function deny(string $messageKey, string $reason): Response
    {
        return response()->json([
            'message' => __($messageKey),
            'reason' => $reason,
        ], 403);
    }
}
