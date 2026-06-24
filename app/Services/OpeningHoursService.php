<?php

namespace App\Services;

use App\Models\Company;

class OpeningHoursService
{
    public function __construct(private VacationService $vacation) {}

    /**
     * The canonical "opening time" object used for both the general company time
     * and a location's custom hours. Day indices are Mon..Sun (0..6).
     *
     * @return array<string, mixed>
     */
    public function defaultOpeningTime(): array
    {
        return [
            'days' => [true, true, true, true, true, false, false],
            'time_mode' => 'same',
            'open' => '09:00',
            'close' => '17:00',
            'times' => array_fill(0, 7, ['open' => '09:00', 'close' => '17:00']),
            'exc' => [],
        ];
    }

    /**
     * The company's general opening time, falling back to a default seeded from
     * the vacation policy's working_days so the open-days stay consistent.
     *
     * @return array<string, mixed>
     */
    public function forCompany(Company $company): array
    {
        if (is_array($company->opening_hours)) {
            return $company->opening_hours;
        }

        $hours = $this->defaultOpeningTime();
        $workingDays = $this->vacation->policyFor($company)->working_days ?? [1, 2, 3, 4, 5];
        $hours['days'] = $this->daysFromWorkingDays($workingDays);

        return $hours;
    }

    /**
     * Persist the company's general opening time and mirror its open-days into
     * the vacation policy's working_days (which drives vacation-day math).
     *
     * @param  array<string, mixed>  $hours
     * @return array<string, mixed>
     */
    public function updateForCompany(Company $company, array $hours): array
    {
        $company->opening_hours = $hours;
        $company->save();

        $workingDays = $this->workingDaysFromDays($hours['days'] ?? []);

        if ($workingDays !== []) {
            $policy = $this->vacation->policyFor($company);
            $policy->working_days = $workingDays;
            $policy->save();
        }

        return $hours;
    }

    /**
     * ISO weekday numbers (1=Mon … 7=Sun) for each open day.
     *
     * @param  array<int, bool>  $days
     * @return array<int, int>
     */
    public function workingDaysFromDays(array $days): array
    {
        $iso = [];
        foreach ($days as $i => $on) {
            if ($on) {
                $iso[] = $i + 1;
            }
        }

        return $iso;
    }

    /**
     * Boolean open-flags Mon..Sun from ISO weekday numbers.
     *
     * @param  array<int, int>  $workingDays
     * @return array<int, bool>
     */
    public function daysFromWorkingDays(array $workingDays): array
    {
        return array_map(fn (int $iso) => in_array($iso, $workingDays, true), range(1, 7));
    }
}
