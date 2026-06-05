<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SwitchCompanyController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        $belongsToCompany = $user->companies()
            ->where('companies.id', $validated['company_id'])
            ->exists();

        if (! $belongsToCompany) {
            throw ValidationException::withMessages([
                'company_id' => ['You do not belong to this company.'],
            ]);
        }

        $user->update(['company_id' => $validated['company_id']]);

        return response()->json([
            'data' => new UserResource($user->fresh()->load(['companies', 'employee.company'])),
            'message' => 'Active company switched successfully.',
        ]);
    }
}
