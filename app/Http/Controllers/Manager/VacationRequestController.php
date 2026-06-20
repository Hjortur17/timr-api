<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vacation\ReviewVacationRequestRequest;
use App\Http\Requests\Vacation\StoreManagerVacationRequestRequest;
use App\Http\Requests\Vacation\UpdateManagerVacationRequestRequest;
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
        $this->authorize('viewAny', VacationRequest::class);

        $query = VacationRequest::query()
            ->with(['employee', 'reviewer'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
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

    public function store(StoreManagerVacationRequestRequest $request): JsonResponse
    {
        $this->authorize('createForEmployee', VacationRequest::class);

        $employee = Employee::findOrFail($request->validated('employee_id'));

        $vacationRequest = $this->service->createForEmployee($employee, $request->user(), $request->validated());

        return response()->json([
            'data' => new VacationRequestResource($vacationRequest),
            'message' => 'Vacation request created.',
        ], 201);
    }

    public function show(VacationRequest $vacationRequest): JsonResponse
    {
        $this->authorize('view', $vacationRequest);

        $vacationRequest->load(['employee', 'reviewer']);

        return response()->json([
            'data' => new VacationRequestResource($vacationRequest),
            'message' => 'Success',
        ]);
    }

    public function review(ReviewVacationRequestRequest $request, VacationRequest $vacationRequest): JsonResponse
    {
        $this->authorize('review', $vacationRequest);

        $reviewed = $this->service->review(
            $vacationRequest,
            $request->user(),
            $request->validated('status'),
            $request->validated('note'),
        );

        return response()->json([
            'data' => new VacationRequestResource($reviewed),
            'message' => 'Vacation request reviewed successfully.',
        ]);
    }

    public function update(UpdateManagerVacationRequestRequest $request, VacationRequest $vacationRequest): JsonResponse
    {
        $this->authorize('update', $vacationRequest);

        $updated = $this->service->update($vacationRequest, $request->validated());

        return response()->json([
            'data' => new VacationRequestResource($updated),
            'message' => 'Vacation request updated.',
        ]);
    }

    public function restore(VacationRequest $vacationRequest): JsonResponse
    {
        $this->authorize('restore', $vacationRequest);

        $restored = $this->service->restore($vacationRequest);

        return response()->json([
            'data' => new VacationRequestResource($restored),
            'message' => 'Vacation request restored.',
        ]);
    }
}
