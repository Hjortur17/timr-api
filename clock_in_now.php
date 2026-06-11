<?php

/*
|--------------------------------------------------------------------------
| Put some employees "on shift right now" (open clock entries)
|--------------------------------------------------------------------------
| Creates clock entries with clocked_in_at set earlier today and
| clocked_out_at = NULL — which the app treats as currently clocked in.
|
| Run with:  php artisan tinker clock_in_now.php
|
| Idempotent: skips any employee who already has an open entry.
*/

use App\Models\Employee;
use App\Models\Shift;
use App\Models\EmployeeShift;
use App\Models\ClockEntry;
use Carbon\Carbon;

$companyId = 1;
$today = Carbon::today();

// The evening shift that is active at the current time.
$shift = Shift::where('company_id', $companyId)->where('title', 'Kvöldvakt')->first();
if (! $shift) {
    echo "No 'Kvöldvakt' shift found — run dummy_data.php first." . PHP_EOL;
    return;
}

// Employees assigned to that shift today...
$assignedIds = EmployeeShift::where('shift_id', $shift->id)
    ->whereDate('date', $today->toDateString())
    ->pluck('employee_id')
    ->all();

// ...plus a couple more active employees, so the floor looks busy.
$extraIds = Employee::where('company_id', $companyId)
    ->where('is_active', true)
    ->whereNotIn('id', $assignedIds)
    ->orderBy('id')
    ->limit(2)
    ->pluck('id')
    ->all();

$employeeIds = array_values(array_unique(array_merge($assignedIds, $extraIds)));

$onShift = [];
$i = 0;
foreach ($employeeIds as $employeeId) {
    // Already clocked in (open entry)? Leave them be.
    $alreadyOpen = ClockEntry::where('employee_id', $employeeId)
        ->whereNull('clocked_out_at')
        ->exists();
    if ($alreadyOpen) {
        $employee = Employee::find($employeeId);
        $onShift[] = ($employee?->name ?? "#$employeeId") . ' (already on shift)';
        continue;
    }

    // Clocked in a bit after the shift started (15:00), staggered per person.
    $clockIn = $today->copy()->setTime(15, 0)->addMinutes($i * 7 + 3);

    ClockEntry::create([
        'shift_id'       => $shift->id,
        'employee_id'    => $employeeId,
        'clocked_in_at'  => $clockIn,
        'clocked_out_at' => null,
        'clock_in_lat'   => 63.8804 + (rand(-40, 40) / 100000),
        'clock_in_lng'   => -22.4495 + (rand(-40, 40) / 100000),
    ]);

    $employee = Employee::find($employeeId);
    $onShift[] = ($employee?->name ?? "#$employeeId") . ' (clocked in ' . $clockIn->format('H:i') . ')';
    $i++;
}

echo "On shift right now (" . count($onShift) . "):" . PHP_EOL;
foreach ($onShift as $line) {
    echo "  - {$line}" . PHP_EOL;
}
echo "Done ✅" . PHP_EOL;
