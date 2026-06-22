<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates access based on the company's subscription lifecycle.
 *
 * Audiences:
 *  - "manager": once the trial ends the manager becomes read-only — GET is
 *    allowed (so they can browse and reach billing) but writes return 402.
 *  - "employee": employees keep working through the grace window; once fully
 *    expired all employee routes return 402.
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next, string $audience = 'manager'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->company_id) {
            abort(403, 'No active company.');
        }

        $subscription = Subscription::where('company_id', $user->company_id)->first();

        // No subscription record (should not happen after backfill) — don't block.
        if ($subscription === null) {
            return $next($request);
        }

        if ($audience === 'employee') {
            if (! $subscription->employeeCanWork()) {
                $this->deny();
            }

            return $next($request);
        }

        // Manager audience: block only writes once the trial has ended.
        $isWrite = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        if ($isWrite && ! $subscription->managerCanWrite()) {
            $this->deny();
        }

        return $next($request);
    }

    private function deny(): never
    {
        abort(response()->json([
            'message' => 'Your subscription is no longer active.',
            'reason' => 'subscription_inactive',
        ], Response::HTTP_PAYMENT_REQUIRED));
    }
}
