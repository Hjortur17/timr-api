<?php

namespace App\Services;

use App\Models\EmployeeShift;
use App\Models\ShiftTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ShiftTemplateService
{
    public function listForCompany(): Collection
    {
        return ShiftTemplate::query()
            ->with(['shift', 'employees'])
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ShiftTemplate
    {
        $employeeIds = $data['employee_ids'] ?? [];
        unset($data['employee_ids']);

        $data['cycle_length_days'] = array_sum($data['blocks']);

        $template = ShiftTemplate::create($data);

        $this->syncEmployees($template, $employeeIds);

        return $template->load(['shift', 'employees']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShiftTemplate $template, array $data): ShiftTemplate
    {
        $employeeIds = $data['employee_ids'] ?? null;
        unset($data['employee_ids']);

        if (isset($data['blocks'])) {
            $data['cycle_length_days'] = array_sum($data['blocks']);
        }

        $template->update($data);

        if ($employeeIds !== null) {
            $this->syncEmployees($template, $employeeIds);
        }

        return $template->fresh(['shift', 'employees']);
    }

    public function delete(ShiftTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Generate shift assignments from a template for a date range.
     *
     * The algorithm distributes work blocks among employees in rotation.
     * For blocks [2, 2, 3] with employees [A, B]:
     *   Cycle 0: A(2 days), B(2 days), A(3 days)
     *   Cycle 1: B(2 days), A(2 days), B(3 days)
     *
     * Formula: employee_index = (cycle_index * num_blocks + block_index) % num_employees
     *
     * @return int Number of assignments created
     */
    public function generateSchedule(ShiftTemplate $template, string $startDate, string $endDate): int
    {
        $template->load(['shift', 'employees']);

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $blocks = $template->blocks;
        $cycleLength = $template->cycle_length_days;
        $employees = $template->employees->values();
        $numEmployees = $employees->count();
        $numBlocks = count($blocks);
        $shiftId = $template->shift_id;

        if ($numEmployees === 0 || $numBlocks === 0) {
            return 0;
        }

        $created = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            $daysSinceStart = $start->diffInDays($current);
            $cycleIndex = intdiv($daysSinceStart, $cycleLength);
            $dayInCycle = $daysSinceStart % $cycleLength;

            // Determine which block this day falls in
            $cumulativeDays = 0;
            $blockIndex = 0;

            foreach ($blocks as $i => $blockSize) {
                if ($dayInCycle < $cumulativeDays + $blockSize) {
                    $blockIndex = $i;
                    break;
                }
                $cumulativeDays += $blockSize;
            }

            // Round-robin employee assignment
            $employeeIndex = ($cycleIndex * $numBlocks + $blockIndex) % $numEmployees;
            $employee = $employees[$employeeIndex];

            // Skip if this assignment already exists
            $exists = EmployeeShift::query()
                ->where('shift_id', $shiftId)
                ->where('employee_id', $employee->id)
                ->whereDate('date', $current->toDateString())
                ->exists();

            if (! $exists) {
                EmployeeShift::create([
                    'shift_id' => $shiftId,
                    'employee_id' => $employee->id,
                    'date' => $current->toDateString(),
                    'published' => false,
                ]);
                $created++;
            }

            $current->addDay();
        }

        return $created;
    }

    /**
     * Sync employees to the template pivot with sort_order preserved.
     *
     * @param  array<int, int>  $employeeIds
     */
    private function syncEmployees(ShiftTemplate $template, array $employeeIds): void
    {
        $syncData = [];

        foreach ($employeeIds as $index => $employeeId) {
            $syncData[$employeeId] = ['sort_order' => $index];
        }

        $template->employees()->sync($syncData);
    }
}
