<?php

namespace App\Http\Middleware;

use App\Enums\CompanyRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyRole
{
    /**
     * @param  string  ...$roles  Comma-separated CompanyRole values (e.g. "owner,admin")
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->company_id) {
            abort(403, 'No active company.');
        }

        $allowed = array_map(
            fn (string $r) => CompanyRole::from($r),
            $roles,
        );

        if (! $user->hasCompanyRole($allowed)) {
            abort(403, 'Insufficient company role.');
        }

        return $next($request);
    }
}
