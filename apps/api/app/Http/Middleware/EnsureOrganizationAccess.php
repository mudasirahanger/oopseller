<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private const WRITE_PATHS_ALLOWED_FOR_VIEWERS = ['api/v1/auth/logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $request->header('X-Organization-Id') ?: $request->user()?->current_organization_id;
        $membership = $organizationId
            ? $request->user()?->organizations()->whereKey($organizationId)->first()
            : null;
        abort_unless($membership, 403, 'Organization access denied.');

        $role = (string) ($membership->pivot->role ?? 'member');
        $request->attributes->set('organization_id', (int) $organizationId);
        $request->attributes->set('organization_role', $role);

        if ($role === 'viewer'
            && ! in_array($request->method(), self::READ_METHODS, true)
            && ! in_array($request->path(), self::WRITE_PATHS_ALLOWED_FOR_VIEWERS, true)
            && ! $request->user()->is_platform_admin) {
            abort(403, 'Your role only allows read access in this organization.');
        }

        return $next($request);
    }
}
