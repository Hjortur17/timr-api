<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacationOverviewController extends Controller
{
    /**
     * Company-wide vacation overview for the visible month. Available to any
     * authenticated company member (employee or manager) so colleagues can see
     * who is away. Leave types are only revealed for the viewer's own requests,
     * unless the viewer is a manager.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No active company.');

        $anchor = $this->resolveMonth($request->query('month'));
        $start = $anchor->startOfMonth();
        $end = $anchor->endOfMonth();

        $isManager = $user->isManager();

        $currentEmployeeId = Employee::query()
            ->where('user_id', $user->id)
            ->value('id');

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
            ])
            ->values();

        $requests = VacationRequest::query()
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', $end->format('Y-m-d'))
            ->whereDate('end_date', '>=', $start->format('Y-m-d'))
            ->orderBy('start_date')
            ->get(['id', 'employee_id', 'start_date', 'end_date', 'status', 'type'])
            ->map(function (VacationRequest $vacationRequest) use ($isManager, $currentEmployeeId) {
                $canSeeType = $isManager || $vacationRequest->employee_id === $currentEmployeeId;

                return [
                    'id' => $vacationRequest->id,
                    'employee_id' => $vacationRequest->employee_id,
                    'start_date' => $vacationRequest->start_date?->format('Y-m-d'),
                    'end_date' => $vacationRequest->end_date?->format('Y-m-d'),
                    'status' => $vacationRequest->status?->value,
                    'type' => $canSeeType ? $vacationRequest->type?->value : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'month' => $anchor->format('Y-m'),
                'current_employee_id' => $currentEmployeeId,
                'employees' => $employees,
                'requests' => $requests,
            ],
            'message' => 'Success',
        ]);
    }

    private function resolveMonth(?string $month): CarbonImmutable
    {
        if ($month) {
            $parsed = CarbonImmutable::createFromFormat('Y-m', $month);

            if ($parsed !== false) {
                return $parsed->startOfMonth();
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
