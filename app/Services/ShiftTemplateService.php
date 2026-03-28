<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\ShiftTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ShiftTemplateService
{
    public function listForCompany(): Collection
    {
        return ShiftTemplate::query()
            ->with(['entries.shift', 'entries.employee'])
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ShiftTemplate
    {
        $entries = $data['entries'] ?? [];
        unset($data['entries']);

        $template = ShiftTemplate::create($data);

        foreach ($entries as $entry) {
            $template->entries()->create($entry);
        }

        return $template->load(['entries.shift', 'entries.employee']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShiftTemplate $template, array $data): ShiftTemplate
    {
        $entries = $data['entries'] ?? null;
        unset($data['entries']);

        $template->update($data);

        if ($entries !== null) {
            $template->entries()->delete();

            foreach ($entries as $entry) {
                $template->entries()->create($entry);
            }
        }

        return $template->fresh(['entries.shift', 'entries.employee']);
    }

    public function delete(ShiftTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Generate shift assignments from a template for a date range.
     *
     * The algorithm walks each day in the range, computes its position
     * within the template cycle (day_offset), and creates unpublished
     * EmployeeShift records for every matching entry.
     *
     * If an entry has no employee_id, it assigns the shift to ALL
     * employees in the company. If employee_id is set, it assigns
     * only to that specific employee.
     *
     * Duplicate assignments (same shift + employee + date) are skipped.
     *
     * @return int Number of assignments created
     */
    public function generateSchedule(ShiftTemplate $template, string $startDate, string $endDate): int
    {
        $template->load(['entries.shift', 'entries.employee']);

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $cycleLength = $template->cycle_length_days;

        // Group entries by day_offset for efficient lookup
        $entriesByOffset = $template->entries->groupBy('day_offset');

        // If any entries have no employee_id, we need all company employees
        $needAllEmployees = $template->entries->contains(fn ($entry) => $entry->employee_id === null);
        $allEmployees = $needAllEmployees
            ? Employee::query()->where('is_active', true)->get()
            : new Collection;

        $created = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            $dayInCycle = $start->diffInDays($current) % $cycleLength;
            $dayEntries = $entriesByOffset->get($dayInCycle, collect());

            foreach ($dayEntries as $entry) {
                $employees = $entry->employee_id
                    ? collect([$entry->employee])
                    : $allEmployees;

                foreach ($employees as $employee) {
                    if (! $employee) {
                        continue;
                    }

                    // Skip if this assignment already exists
                    $exists = EmployeeShift::query()
                        ->where('shift_id', $entry->shift_id)
                        ->where('employee_id', $employee->id)
                        ->whereDate('date', $current->toDateString())
                        ->exists();

                    if (! $exists) {
                        EmployeeShift::create([
                            'shift_id' => $entry->shift_id,
                            'employee_id' => $employee->id,
                            'date' => $current->toDateString(),
                            'published' => false,
                        ]);
                        $created++;
                    }
                }
            }

            $current->addDay();
        }

        return $created;
    }
}
