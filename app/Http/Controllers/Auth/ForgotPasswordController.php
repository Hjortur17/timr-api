<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Vinsamlegast bíðið áður en þú reynir aftur.',
            ], 429);
        }

        return response()->json([
            'message' => 'Ef netfangið er skráð hjá okkur munum við senda endurstillingartengil.',
        ]);
    }
}
