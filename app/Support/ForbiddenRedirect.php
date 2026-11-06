<?php

namespace App\Support;

use App\Http\Traits\WithNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a bare 403 page in the admin panel into a redirect with a notification.
 *
 * Page visits land on the user's home page (dashboard, or profile for roles
 * without admin sections); actions go back to the page they were sent from.
 * API, broadcasting auth and plain JSON requests keep their 403 untouched.
 */
class ForbiddenRedirect
{
    use WithNotification;

    public function handle(Response $response, Request $request): Response
    {
        if (! $this->shouldRedirect($response, $request)) {
            return $response;
        }

        $landingRoute = $this->landingRoute($request->user());

        // The home page itself is forbidden — redirecting would loop.
        if ($request->routeIs($landingRoute)) {
            return $response;
        }

        $this->notifyError('notifications.forbidden');

        if ($request->isMethod('GET')) {
            return redirect()->route($landingRoute);
        }

        return redirect()->back(fallback: route($landingRoute));
    }

    private function shouldRedirect(Response $response, Request $request): bool
    {
        if ($response->getStatusCode() !== Response::HTTP_FORBIDDEN) {
            return false;
        }

        if (! $request->user() instanceof User) {
            return false;
        }

        if ($request->is('api/*') || $request->is('broadcasting/*')) {
            return false;
        }

        return $request->header('X-Inertia') !== null || ! $request->expectsJson();
    }

    private function landingRoute(User $user): string
    {
        return $user->role->canAccessAdminSections() ? 'dashboard' : 'profile.edit';
    }
}
