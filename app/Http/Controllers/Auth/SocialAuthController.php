<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function __construct(private SocialAuthService $socialAuthService) {}

    /**
     * Redirect to the OAuth provider's consent screen.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $provider = $this->resolveProvider($provider);

        return Socialite::driver($provider->value)->stateless()->redirect();
    }

    /**
     * Handle the OAuth callback from the provider.
     *
     * Redirects to the frontend with the auth token so the SPA can store it.
     */
    public function callback(string $provider): RedirectResponse
    {
        $provider = $this->resolveProvider($provider);

        $socialUser = Socialite::driver($provider->value)->stateless()->user();

        $result = $this->socialAuthService->authenticateOrCreate($provider, $socialUser);

        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        return redirect()->to($frontendUrl.'/auth/callback?'.http_build_query([
            'token' => $result['token'],
            'is_new' => $result['is_new'] ? '1' : '0',
        ]));
    }

    /**
     * Handle mobile/native SDK flow — client sends the provider token directly.
     */
    public function token(Request $request, string $provider): JsonResponse
    {
        $provider = $this->resolveProvider($provider);

        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $socialUser = Socialite::driver($provider->value)->stateless()->userFromToken($request->input('token'));

        $result = $this->socialAuthService->authenticateOrCreate($provider, $socialUser);

        return response()->json([
            'data' => new UserResource($result['user']->load('companies')),
            'token' => $result['token'],
            'is_new' => $result['is_new'],
            'message' => $result['is_new'] ? 'Registration successful.' : 'Login successful.',
        ], $result['is_new'] ? 201 : 200);
    }

    private function resolveProvider(string $provider): SocialProvider
    {
        $resolved = SocialProvider::tryFrom($provider);

        if (! $resolved) {
            abort(422, 'Unsupported social provider. Supported: '.implode(', ', SocialProvider::values()));
        }

        return $resolved;
    }
}
