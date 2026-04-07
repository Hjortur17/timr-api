<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClockEntry\ClockEntryIndexRequest;
use App\Http\Requests\ClockEntry\StoreClockEntryRequest;
use App\Http\Requests\ClockEntry\UpdateClockEntryRequest;
use App\Http\Resources\ClockEntryResource;
use App\Models\ClockEntry;
use Illuminate\Http\JsonResponse;

class ClockEntryController extends Controller
{
    public function index(ClockEntryIndexRequest $request): JsonResponse
    {
        $query = ClockEntry::query()
            ->with(['employee', 'shift'])
            ->orderByDesc('clocked_in_at');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('from')) {
            $query->where('clocked_in_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('clocked_in_at', '<=', $request->date('to')->endOfDay());
        }

        $entries = $query->get();

        return response()->json([
            'data' => ClockEntryResource::collection($entries),
            'message' => 'Success',
        ]);
    }

    public function summary(ClockEntryIndexRequest $request): JsonResponse
    {
        $query = ClockEntry::query();

        if ($request->filled('from')) {
            $query->where('clocked_in_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('clocked_in_at', '<=', $request->date('to')->endOfDay());
        }

        $entries = $query->with('employee')->get();

        $grouped = $entries->groupBy('employee_id');

        $data = $grouped->map(function ($employeeEntries) {
            $employee = $employeeEntries->first()->employee;
            $totalMinutes = $employeeEntries->sum(
                fn ($entry) => $entry->clocked_in_at->diffInMinutes($entry->clocked_out_at ?? now())
            );

            return [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                ],
                'total_minutes' => $totalMinutes,
                'entry_count' => $employeeEntries->count(),
                'last_clocked_in_at' => $employeeEntries->max('clocked_in_at'),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'message' => 'Success',
        ]);
    }

    public function store(StoreClockEntryRequest $request): JsonResponse
    {
        $entry = ClockEntry::create($request->validated());

        return response()->json([
            'data' => new ClockEntryResource($entry->load(['employee', 'shift'])),
            'message' => 'Clock entry created.',
        ], 201);
    }

    public function update(UpdateClockEntryRequest $request, ClockEntry $clockEntry): JsonResponse
    {
        $clockEntry->update($request->validated());

        return response()->json([
            'data' => new ClockEntryResource($clockEntry->load(['employee', 'shift'])),
            'message' => 'Clock entry updated.',
        ]);
    }

    public function destroy(ClockEntry $clockEntry): JsonResponse
    {
        $clockEntry->delete();

        return response()->json([
            'message' => 'Clock entry deleted.',
        ]);
    }
}
