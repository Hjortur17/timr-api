<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftResource;
use App\Models\Employee;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(private ShiftService $shiftService) {}

    public function index(Request $request): JsonResponse
    {
        $employee = Employee::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $shifts = $this->shiftService->listForEmployee($employee);

        return response()->json([
            'data' => ShiftResource::collection($shifts),
            'message' => 'Success',
        ]);
    }
}
