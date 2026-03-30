<?php

namespace App\Http\Controllers\Manager;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\StoreShiftAssignmentRequest;
use App\Http\Requests\Shift\UpdateShiftAssignmentRequest;
use App\Http\Resources\ShiftAssignmentResource;
use App\Models\EmployeeShift;
use App\Notifications\ShiftChangedNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        $assignments = EmployeeShift::query()
            ->whereBetween('date', [$request->input('from'), $request->input('to')])
            ->with('shift', 'employee')
            ->join('shifts', 'employee_shift.shift_id', '=', 'shifts.id')
            ->orderBy('shifts.start_time')
            ->select('employee_shift.*')
            ->get();

        return response()->json([
            'data' => ShiftAssignmentResource::collection($assignments),
            'message' => 'Success',
        ]);
    }

    public function store(StoreShiftAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $assignment = EmployeeShift::create([
                'shift_id' => $validated['shift_id'],
                'employee_id' => $validated['employee_id'],
                'date' => $validated['date'],
                'published' => $validated['published'] ?? false,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'This shift is already assigned to this employee on this date.',
                'errors' => ['date' => ['This shift is already assigned to this employee on this date.']],
            ], 422);
        }

        $assignment->load('shift', 'employee');

        return response()->json([
            'data' => new ShiftAssignmentResource($assignment),
            'message' => 'Shift assignment created successfully.',
        ], 201);
    }

    public function update(UpdateShiftAssignmentRequest $request, EmployeeShift $shiftAssignment): JsonResponse
    {
        $this->authorize('update', $shiftAssignment);

        $shiftAssignment->update($request->validated());

        $shiftAssignment->load('shift', 'employee');

        return response()->json([
            'data' => new ShiftAssignmentResource($shiftAssignment),
            'message' => 'Shift assignment updated successfully.',
        ]);
    }

    public function destroy(EmployeeShift $shiftAssignment): JsonResponse
    {
        $this->authorize('delete', $shiftAssignment);

        $shiftAssignment->load('shift', 'employee.notificationPreferences');

        $shouldNotify = $shiftAssignment->published
            && $shiftAssignment->employee?->prefersNotification(NotificationType::ShiftChanged);

        $shiftAssignment->delete();

        if ($shouldNotify) {
            $shiftAssignment->employee->notify(
                new ShiftChangedNotification($shiftAssignment, 'deleted')
            );
        }

        return response()->json([
            'message' => 'Shift assignment deleted successfully.',
        ]);
    }
}
