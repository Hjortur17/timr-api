<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clock\ClockInRequest;
use App\Http\Resources\ClockEntryResource;
use App\Models\ClockEntry;
use App\Models\Shift;
use App\Services\ClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClockController extends Controller
{
    public function __construct(private ClockService $clockService) {}

    public function clockIn(ClockInRequest $request): JsonResponse
    {
        $shift = Shift::findOrFail($request->validated('shift_id'));

        Gate::authorize('clockIn', [ClockEntry::class, $shift]);

        $entry = $this->clockService->clockIn(
            $request->user(),
            $shift,
            $request->validated('latitude'),
            $request->validated('longitude'),
        );

        return response()->json([
            'data' => new ClockEntryResource($entry),
            'message' => 'Clocked in successfully.',
        ], 201);
    }

    public function clockOut(Request $request): JsonResponse
    {
        $entry = $this->clockService->clockOut($request->user());

        return response()->json([
            'data' => new ClockEntryResource($entry),
            'message' => 'Clocked out successfully.',
        ]);
    }
}
