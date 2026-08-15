<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Throwable;

class ValidatePermission
{
    /**
     * Handle an incoming request.
     *
     * Defensive contract: never lets the request reach the controller if
     * the actor cannot satisfy the configured permission. Unauthenticated
     * requests, unknown-permission lookups and missing-guard scenarios all
     * resolve to `401 Unauthorized` (preserved for backwards compatibility
     * with the existing API contract that other modules rely on).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $permission = null, $guard = "")
    {
        $user = $request->user();
        if ($user === null) {
            abort(401, 'This action is unauthorized.');
        }

        $permissions = is_array($permission)
            ? $permission
            : explode('|', $permission ?? '');

        foreach ($permissions as $singlePermission) {
            try {
                if ($user->hasPermissionTo($singlePermission, $guard)) {
                    return $next($request);
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        abort(401, 'This action is unauthorized.');
    }
}
