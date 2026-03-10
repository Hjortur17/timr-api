<?php

namespace App\Http\Controllers\Auth;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCompanyController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->company_id !== null) {
            throw ValidationException::withMessages([
                'company' => ['You already belong to a company.'],
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $company = DB::transaction(function () use ($user, $validated) {
            $company = Company::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::random(5),
            ]);

            $user->update([
                'company_id' => $company->id,
                'onboarding_step' => 2,
            ]);

            $user->companies()->attach($company->id, ['role' => CompanyRole::Owner->value]);

            return $company;
        });

        return response()->json([
            'data' => new UserResource($user->fresh()->load('companies')),
            'message' => 'Company created successfully.',
        ], 201);
    }
}
