<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShiftResource;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(private ShiftService $shiftService) {}

    public function index(Request $request): JsonResponse
    {
        $shifts = $this->shiftService->listForEmployee($request->user());

        return response()->json([
            'data' => ShiftResource::collection($shifts),
            'message' => 'Success',
        ]);
    }
}
