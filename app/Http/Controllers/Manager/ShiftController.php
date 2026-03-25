<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\PublishShiftsRequest;
use App\Http\Requests\Shift\StoreShiftRequest;
use App\Http\Requests\Shift\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    public function __construct(private ShiftService $shiftService) {}

    public function index(): JsonResponse
    {
        $shifts = $this->shiftService->listForCompany();

        return response()->json([
            'data' => ShiftResource::collection($shifts),
            'message' => 'Success',
        ]);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = $this->shiftService->create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'data' => new ShiftResource($shift),
            'message' => 'Shift created successfully.',
        ], 201);
    }

    public function update(UpdateShiftRequest $request, Shift $shift): JsonResponse
    {
        $this->authorize('update', $shift);

        $shift = $this->shiftService->update($shift, $request->validated());

        return response()->json([
            'data' => new ShiftResource($shift),
            'message' => 'Shift updated successfully.',
        ]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('delete', $shift);

        $this->shiftService->delete($shift);

        return response()->json([
            'message' => 'Shift deleted successfully.',
        ]);
    }

    public function publish(PublishShiftsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $updated = $this->shiftService->publishAssignmentsInRange(
            $request->validated('from'),
            $request->validated('to'),
        );

        return response()->json([
            'message' => 'Shifts published successfully.',
            'updated_count' => $updated,
        ]);
    }
}
