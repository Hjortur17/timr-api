<?php

namespace App\Services;

use App\Enums\VacationRequestStatus;
use App\Enums\VacationRequestType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationPolicy;
use App\Models\VacationRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class VacationService
{
    /** @var array<int, array<string, true>> */
    private array $holidayCache = [];

    public function __construct(private IcelandicHolidayService $holidays) {}

    /**
     * @param  array<int, int>  $openDays  ISO weekday numbers the company is open (1=Mon … 7=Sun).
     */
    public function calculateWorkingDays(CarbonInterface $start, CarbonInterface $end, array $openDays = [1, 2, 3, 4, 5]): int
    {
        if ($end->lessThan($start)) {
            return 0;
        }

        $count = 0;
        $cursor = CarbonImmutable::parse($start->format('Y-m-d'));
        $last = CarbonImmutable::parse($end->format('Y-m-d'));

        while ($cursor->lessThanOrEqualTo($last)) {
            if (in_array($cursor->isoWeekday(), $openDays, true) && ! $this->isHoliday($cursor)) {
                $count++;
            }
            $cursor = $cursor->addDay();
        }

        return $count;
    }

    /**
     * @return array<int, int>
     */
    private function openDaysFor(Employee $employee): array
    {
        $company = $employee->company()->withoutGlobalScope('company')->firstOrFail();

        return $this->policyFor($company)->working_days ?? [1, 2, 3, 4, 5];
    }

    public function policyFor(Company $company): VacationPolicy
    {
        return VacationPolicy::withoutGlobalScope('company')->firstOrCreate(
            ['company_id' => $company->id],
            [
                'default_days_per_year' => 24,
                'vacation_year_start_month' => 5,
                'vacation_year_start_day' => 1,
                'working_days' => [1, 2, 3, 4, 5],
                'opening_hours' => null,
                'allow_carry_over' => false,
                'max_carry_over_days' => null,
            ],
        );
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function vacationYearRange(VacationPolicy $policy, CarbonInterface $reference): array
    {
        $year = $reference->year;
        $candidateStart = CarbonImmutable::create($year, $policy->vacation_year_start_month, $policy->vacation_year_start_day);

        if ($reference->lessThan($candidateStart)) {
            $start = $candidateStart->subYear();
        } else {
            $start = $candidateStart;
        }

        $end = $start->addYear()->subDay();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceFor(Employee $employee): array
    {
        $company = $employee->company()->withoutGlobalScope('company')->firstOrFail();
        $policy = $this->policyFor($company);
        $range = $this->vacationYearRange($policy, CarbonImmutable::now());

        $query = VacationRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('start_date', '<=', $range['end'])
            ->whereDate('end_date', '>=', $range['start']);

        $query->where('type', VacationRequestType::Holiday->value);

        $used = (clone $query)
            ->where('status', VacationRequestStatus::Approved->value)
            ->sum('working_days_count');

        $pending = (clone $query)
            ->where('status', VacationRequestStatus::Pending->value)
            ->sum('working_days_count');

        $entitled = $policy->default_days_per_year;
        $remaining = $entitled - (int) $used - (int) $pending;

        return [
            'entitled' => $entitled,
            'used' => (int) $used,
            'pending' => (int) $pending,
            'remaining' => $remaining,
            'working_days' => $policy->working_days ?? [1, 2, 3, 4, 5],
            'vacation_year_start' => $range['start']->format('Y-m-d'),
            'vacation_year_end' => $range['end']->format('Y-m-d'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRequest(Employee $employee, array $data): VacationRequest
    {
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $type = VacationRequestType::from($data['type'] ?? VacationRequestType::Holiday->value);

        $this->assertNoOverlap($employee, $start, $end);

        $workingDays = $this->calculateWorkingDays($start, $end, $this->openDaysFor($employee));

        if ($type->isDeductible()) {
            $balance = $this->balanceFor($employee);

            if ($workingDays > $balance['remaining']) {
                throw ValidationException::withMessages([
                    'end_date' => 'This request exceeds your remaining vacation days.',
                ]);
            }
        }

        return VacationRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'working_days_count' => $workingDays,
            'status' => VacationRequestStatus::Pending->value,
            'type' => $type->value,
            'employee_note' => $data['note'] ?? null,
        ]);
    }

    /**
     * Manager-created vacation for an employee. Auto-approved and not subject to
     * the balance limit (a manager may push an employee's remaining days negative).
     *
     * @param  array<string, mixed>  $data
     */
    public function createForEmployee(Employee $employee, User $manager, array $data): VacationRequest
    {
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $type = VacationRequestType::from($data['type'] ?? VacationRequestType::Holiday->value);

        $this->assertNoOverlap($employee, $start, $end);

        $request = VacationRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'working_days_count' => $this->calculateWorkingDays($start, $end, $this->openDaysFor($employee)),
            'status' => VacationRequestStatus::Approved->value,
            'type' => $type->value,
            'employee_note' => $data['note'] ?? null,
            'reviewed_by' => $manager->id,
            'reviewed_at' => now(),
        ]);

        return $request->fresh(['employee', 'reviewer']);
    }

    /**
     * Manager edit of an existing request. No balance check, any status, employee unchanged.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(VacationRequest $request, array $data): VacationRequest
    {
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $type = VacationRequestType::from($data['type'] ?? $request->type?->value ?? VacationRequestType::Holiday->value);

        $this->assertNoOverlap($request->employee, $start, $end, $request->id);

        $request->update([
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'working_days_count' => $this->calculateWorkingDays($start, $end, $this->openDaysFor($request->employee)),
            'type' => $type->value,
            'employee_note' => $data['note'] ?? null,
        ]);

        return $request->fresh(['employee', 'reviewer']);
    }

    public function restore(VacationRequest $request): VacationRequest
    {
        if ($request->status !== VacationRequestStatus::Denied) {
            throw ValidationException::withMessages([
                'status' => 'Only denied requests can be restored.',
            ]);
        }

        $request->update([
            'status' => VacationRequestStatus::Pending->value,
            'reviewer_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return $request->fresh(['employee', 'reviewer']);
    }

    public function cancel(VacationRequest $request): VacationRequest
    {
        if ($request->status !== VacationRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be cancelled.',
            ]);
        }

        $request->update([
            'status' => VacationRequestStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);

        return $request->fresh();
    }

    public function review(VacationRequest $request, User $reviewer, string $status, ?string $note): VacationRequest
    {
        if ($request->status !== VacationRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be reviewed.',
            ]);
        }

        $request->update([
            'status' => $status,
            'reviewer_note' => $note,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $request->fresh(['employee', 'reviewer']);
    }

    private function assertNoOverlap(Employee $employee, CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): void
    {
        $overlaps = VacationRequest::query()
            ->where('employee_id', $employee->id)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereIn('status', [
                VacationRequestStatus::Pending->value,
                VacationRequestStatus::Approved->value,
            ])
            ->whereDate('start_date', '<=', $end->format('Y-m-d'))
            ->whereDate('end_date', '>=', $start->format('Y-m-d'))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => 'This date range overlaps with an existing vacation request.',
            ]);
        }
    }

    private function isHoliday(CarbonImmutable $date): bool
    {
        $year = $date->year;

        if (! isset($this->holidayCache[$year])) {
            $this->holidayCache[$year] = array_fill_keys($this->holidays->getHolidays($year), true);
        }

        return isset($this->holidayCache[$year][$date->format('Y-m-d')]);
    }
}
