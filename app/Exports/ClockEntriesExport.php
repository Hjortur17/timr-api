<?php

namespace App\Exports;

use App\Models\ClockEntry;
use App\Services\IcelandicHolidayService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClockEntriesExport implements FromArray, ShouldAutoSize, WithHeadings
{
    private IcelandicHolidayService $holidayService;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var list<string> */
    private array $activeHeadings = [];

    public function __construct(
        private ?int $employeeId = null,
        private ?string $from = null,
        private ?string $to = null,
    ) {
        $this->holidayService = new IcelandicHolidayService;
        $this->build();
    }

    public function array(): array
    {
        return collect($this->rows)->map(function ($row) {
            $out = [];
            foreach ($this->activeHeadings as $key) {
                $out[] = $row[$key] ?? '';
            }

            return $out;
        })->all();
    }

    public function headings(): array
    {
        $labels = [
            'kennitala' => 'Kennitala',
            'nafn' => 'Nafn',
            'dagvinna' => 'Dagvinna',
            'yfirvinna' => 'Yfirvinna',
            'storhatid' => 'Stórhátíðarkaup',
        ];

        return array_map(fn ($key) => $labels[$key], $this->activeHeadings);
    }

    private function build(): void
    {
        $query = ClockEntry::query()
            ->with(['employee', 'shift'])
            ->whereNotNull('clocked_out_at')
            ->orderBy('employee_id');

        if ($this->employeeId) {
            $query->where('employee_id', $this->employeeId);
        }

        if ($this->from) {
            $query->where('clocked_in_at', '>=', $this->from);
        }

        if ($this->to) {
            $query->where('clocked_in_at', '<=', Carbon::parse($this->to)->endOfDay());
        }

        $entries = $query->get();
        $grouped = $entries->groupBy('employee_id');

        $years = $entries->map(fn ($e) => $e->clocked_in_at->year)->unique()->values()->all();
        $holidays = [];
        foreach ($years as $year) {
            foreach ($this->holidayService->getHolidays($year) as $date) {
                $holidays[$date] = true;
            }
        }

        $hasYfirvinna = false;
        $hasStorhatid = false;

        $this->rows = $grouped->map(function ($employeeEntries) use ($holidays, &$hasYfirvinna, &$hasStorhatid) {
            $employee = $employeeEntries->first()->employee;
            $dagvinnaMin = 0;
            $yfirvinnaMin = 0;
            $storhatidMin = 0;
            $daysWorked = [];

            foreach ($employeeEntries as $entry) {
                $clockIn = $entry->clocked_in_at;
                $clockOut = $entry->clocked_out_at;
                $date = $clockIn->format('Y-m-d');
                $daysWorked[$date] = true;

                $totalMinutes = (int) $clockIn->diffInMinutes($clockOut);
                $isHoliday = isset($holidays[$date]);
                $isHalfHoliday = $this->isHalfDayHoliday($date);
                $dayOfWeek = $clockIn->dayOfWeekIso;

                if ($isHoliday) {
                    if ($isHalfHoliday) {
                        $splitHour = Carbon::parse($date.' 13:00:00');
                        $beforeMin = $this->minutesBetween($clockIn, min($clockOut, $splitHour));
                        $duringMin = $this->minutesBetween(max($clockIn, $splitHour), $clockOut);

                        if ($beforeMin > 0) {
                            $this->classifyRegularMinutes($beforeMin, $dagvinnaMin, $yfirvinnaMin);
                        }
                        $storhatidMin += max(0, $duringMin);
                    } else {
                        $storhatidMin += $totalMinutes;
                    }
                } elseif ($dayOfWeek >= 6) {
                    $yfirvinnaMin += $totalMinutes;
                } else {
                    $this->classifyRegularMinutes($totalMinutes, $dagvinnaMin, $yfirvinnaMin);
                }
            }

            if ($yfirvinnaMin > 0) {
                $hasYfirvinna = true;
            }
            if ($storhatidMin > 0) {
                $hasStorhatid = true;
            }

            return [
                'kennitala' => $employee->ssn ?? '',
                'nafn' => $employee->name ?? '',
                'dagvinna' => $this->formatMinutes($dagvinnaMin),
                'yfirvinna' => $yfirvinnaMin > 0 ? $this->formatMinutes($yfirvinnaMin) : '',
                'storhatid' => $storhatidMin > 0 ? $this->formatMinutes($storhatidMin) : '',
            ];
        })->values()->all();

        // Build active headings — only include columns that have data
        $this->activeHeadings = ['kennitala', 'nafn', 'dagvinna'];
        if ($hasYfirvinna) {
            $this->activeHeadings[] = 'yfirvinna';
        }
        if ($hasStorhatid) {
            $this->activeHeadings[] = 'storhatid';
        }
    }

    /**
     * Format minutes as "H,MM" — e.g. 113 minutes → "1,53"
     */
    private function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h.','.$this->padTwo($m);
    }

    private function padTwo(int $value): string
    {
        return str_pad((string) $value, 2, '0', STR_PAD_LEFT);
    }

    private function classifyRegularMinutes(int $minutes, int &$dagvinna, int &$yfirvinna): void
    {
        if ($minutes <= 480) { // 8 hours = 480 minutes
            $dagvinna += $minutes;
        } else {
            $dagvinna += 480;
            $yfirvinna += $minutes - 480;
        }
    }

    private function minutesBetween(Carbon $start, Carbon $end): int
    {
        if ($end <= $start) {
            return 0;
        }

        return (int) $start->diffInMinutes($end);
    }

    private function isHalfDayHoliday(string $date): bool
    {
        $month = (int) substr($date, 5, 2);
        $day = (int) substr($date, 8, 2);

        return ($month === 12 && $day === 24) || ($month === 12 && $day === 31);
    }
}
