<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vacation\UpdateVacationPolicyRequest;
use App\Http\Resources\VacationPolicyResource;
use App\Services\VacationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacationPolicyController extends Controller
{
    public function __construct(private VacationService $service) {}

    public function show(Request $request): JsonResponse
    {
        $policy = $this->service->policyFor($request->user()->company);

        return response()->json([
            'data' => new VacationPolicyResource($policy),
            'message' => 'Success',
        ]);
    }

    public function update(UpdateVacationPolicyRequest $request): JsonResponse
    {
        $policy = $this->service->policyFor($request->user()->company);
        $policy->fill($request->validated())->save();

        return response()->json([
            'data' => new VacationPolicyResource($policy->fresh()),
            'message' => 'Vacation policy updated successfully.',
        ]);
    }
}
