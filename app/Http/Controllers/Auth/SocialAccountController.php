<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    /**
     * List all social accounts linked to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $accounts = SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(fn (SocialAccount $account) => [
                'id' => $account->id,
                'provider' => $account->provider->value,
                'provider_email' => $account->provider_email,
                'avatar_url' => $account->avatar_url,
                'created_at' => $account->created_at,
            ]);

        return response()->json([
            'data' => $accounts,
            'message' => 'Success',
        ]);
    }

    /**
     * Unlink a social account from the authenticated user.
     */
    public function destroy(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        if ($socialAccount->user_id !== $request->user()->id) {
            abort(403);
        }

        // Prevent unlinking if user has no password and this is their only social account
        $user = $request->user();
        $socialAccountCount = SocialAccount::where('user_id', $user->id)->count();

        if ($user->password === null && $socialAccountCount <= 1) {
            return response()->json([
                'message' => 'Þú getur ekki aftengt síðasta innskráningarleiðina. Settu lykilorð fyrst.',
            ], 422);
        }

        $socialAccount->delete();

        return response()->json([
            'message' => 'Tengingu aftengt.',
        ]);
    }
}
