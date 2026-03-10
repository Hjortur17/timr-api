<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $hasEmployee = Employee::query()
            ->withoutGlobalScope('company')
            ->where('user_id', $user->id)
            ->exists();

        if (! $hasEmployee) {
            abort(403, 'Not an employee.');
        }

        return $next($request);
    }
}
