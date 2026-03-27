<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateOnboardingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        $user = $request->user();
        $user->update(['onboarding_step' => $validated['step']]);

        return response()->json([
            'data' => new UserResource($user->fresh()->load('companies')),
            'message' => 'Onboarding step updated.',
        ]);
    }
}
