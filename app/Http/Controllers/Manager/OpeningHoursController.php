<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manager\UpdateOpeningHoursRequest;
use App\Services\OpeningHoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpeningHoursController extends Controller
{
    public function __construct(private OpeningHoursService $service) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->forCompany($request->user()->company),
            'message' => 'Success',
        ]);
    }

    public function update(UpdateOpeningHoursRequest $request): JsonResponse
    {
        $hours = $this->service->updateForCompany($request->user()->company, $request->validated());

        return response()->json([
            'data' => $hours,
            'message' => 'Opening hours updated successfully.',
        ]);
    }
}
