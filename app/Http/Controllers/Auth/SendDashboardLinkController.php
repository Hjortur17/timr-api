<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\DashboardLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

class SendDashboardLinkController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $key = 'send-dashboard-link:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json([
                'message' => 'Vinsamlegast bíðið áður en þú reynir aftur.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        // One-time, expiring link the user opens on their computer. Auth itself
        // is handled by the web app, so this lands them on login with the email
        // prefilled; the plain dashboard URL is included as a reliable fallback.
        $loginUrl = URL::temporarySignedRoute('auth.login-link', now()->addMinutes(30), ['user' => $user->id]);
        $dashboardUrl = config('app.frontend_url').'/dashboard';

        Mail::to($user->email)->send(new DashboardLink($user, $dashboardUrl, $loginUrl));

        return response()->json([
            'message' => 'Tengill sendur.',
        ]);
    }
}
