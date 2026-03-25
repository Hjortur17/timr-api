<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftAssignmentResource;
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

        $assignments = $this->shiftService->listAssignmentsForEmployee(
            $employee,
            $request->query('from'),
            $request->query('to'),
        );

        return response()->json([
            'data' => ShiftAssignmentResource::collection($assignments),
            'message' => 'Success',
        ]);
    }
}
