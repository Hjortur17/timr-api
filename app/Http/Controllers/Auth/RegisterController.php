<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __invoke(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::withoutGlobalScope('company')->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            if (! empty($validated['invite_token'])) {
                $employee = Employee::withoutGlobalScope('company')
                    ->whereNull('user_id')
                    ->where('invite_token', $validated['invite_token'])
                    ->first();

                if (! $employee) {
                    abort(422, 'Þetta boðsboð er ekki gilt eða hefur þegar verið notað.');
                }

                $employee->update([
                    'user_id' => $user->id,
                    'invite_token' => null,
                    'invite_sent_at' => null,
                ]);

                $user->update([
                    'company_id' => $employee->company_id,
                    'onboarding_step' => 6,
                ]);
            }

            return $user;
        });

        return response()->json([
            'data' => new UserResource($user),
            'token' => $user->createToken('auth-token')->plainTextToken,
            'message' => 'Registration successful.',
        ], 201);
    }
}
