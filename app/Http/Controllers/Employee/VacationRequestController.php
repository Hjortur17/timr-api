<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vacation\StoreVacationRequestRequest;
use App\Http\Resources\VacationRequestResource;
use App\Models\Employee;
use App\Models\VacationRequest;
use App\Services\VacationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacationRequestController extends Controller
{
    public function __construct(private VacationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $query = VacationRequest::query()
            ->where('employee_id', $employee->id)
            ->with(['reviewer'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('end_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('start_date', '<=', $to);
        }

        return response()->json([
            'data' => VacationRequestResource::collection($query->get()),
            'message' => 'Success',
        ]);
    }

    public function store(StoreVacationRequestRequest $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $vacationRequest = $this->service->createRequest($employee, $request->validated());

        return response()->json([
            'data' => new VacationRequestResource($vacationRequest),
            'message' => 'Vacation request submitted.',
        ], 201);
    }

    public function cancel(VacationRequest $vacationRequest): JsonResponse
    {
        $this->authorize('cancel', $vacationRequest);

        $cancelled = $this->service->cancel($vacationRequest);

        return response()->json([
            'data' => new VacationRequestResource($cancelled),
            'message' => 'Vacation request cancelled.',
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        return response()->json([
            'data' => $this->service->balanceFor($employee),
            'message' => 'Success',
        ]);
    }

    private function currentEmployee(Request $request): Employee
    {
        return Employee::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
