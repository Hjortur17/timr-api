<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SwitchActiveCompanyRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class ActiveCompanyController extends Controller
{
    public function __invoke(SwitchActiveCompanyRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['company_id' => $request->validated('company_id')]);

        return response()->json([
            'data' => new UserResource($user->fresh()->load('companies')),
        ]);
    }
}
