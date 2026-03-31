<?php

namespace App\Services;

use Carbon\Carbon;

class IcelandicHolidayService
{
    /**
     * Get all Icelandic public holidays (stórhátíðardagar) for a given year.
     *
     * @return string[] Array of date strings in Y-m-d format
     */
    public function getHolidays(int $year): array
    {
        $easter = Carbon::createFromTimestamp(easter_date($year));

        $holidays = [
            // Fixed holidays
            Carbon::create($year, 1, 1)->format('Y-m-d'),   // Nýársdagur
            Carbon::create($year, 5, 1)->format('Y-m-d'),   // Verkalýðsdagurinn
            Carbon::create($year, 6, 17)->format('Y-m-d'),  // Þjóðhátíðardagur
            Carbon::create($year, 12, 24)->format('Y-m-d'), // Aðfangadagur (from 13:00)
            Carbon::create($year, 12, 25)->format('Y-m-d'), // Jóladagur
            Carbon::create($year, 12, 26)->format('Y-m-d'), // Annar í jólum
            Carbon::create($year, 12, 31)->format('Y-m-d'), // Gamlársdagur (from 13:00)

            // Easter-based holidays
            $easter->copy()->subDays(3)->format('Y-m-d'),   // Skírdagur (Maundy Thursday)
            $easter->copy()->subDays(2)->format('Y-m-d'),   // Föstudagurinn langi (Good Friday)
            $easter->format('Y-m-d'),                       // Páskadagur
            $easter->copy()->addDay()->format('Y-m-d'),     // Annar í páskum
            $easter->copy()->addDays(39)->format('Y-m-d'),  // Uppstigningardagur
            $easter->copy()->addDays(49)->format('Y-m-d'),  // Hvítasunnudagur
            $easter->copy()->addDays(50)->format('Y-m-d'),  // Annar í hvítasunnu

            // Variable holidays
            $this->firstDayOfSummer($year),                 // Sumardagurinn fyrsti
            $this->commerceDay($year),                      // Verslunarmannahelgi
        ];

        return $holidays;
    }

    /**
     * Sumardagurinn fyrsti: First Thursday after April 18.
     */
    private function firstDayOfSummer(int $year): string
    {
        $date = Carbon::create($year, 4, 19);

        while ($date->dayOfWeekIso !== 4) { // 4 = Thursday
            $date->addDay();
        }

        return $date->format('Y-m-d');
    }

    /**
     * Verslunarmannadagur: First Monday in August.
     */
    private function commerceDay(int $year): string
    {
        $date = Carbon::create($year, 8, 1);

        while ($date->dayOfWeekIso !== 1) { // 1 = Monday
            $date->addDay();
        }

        return $date->format('Y-m-d');
    }
}
