<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectScribeDocs
{
    /**
     * Restrict access to the generated API docs (/docs) in production.
     *
     * In local/dev the docs stay open for convenience. In production only an
     * authenticated administrator may view them; everyone else gets a 404 so the
     * docs endpoint is not even discoverable.
     *
     * Registered on the docs route through `config/scribe.php` → `laravel.middleware`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || $user->role !== UserRole::Administrator) {
            abort(404);
        }

        return $next($request);
    }
}
