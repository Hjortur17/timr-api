<?php

/*
|--------------------------------------------------------------------------
| Dummy data seeder for company #1  (Icelandic names)
|--------------------------------------------------------------------------
| Run with:   php artisan tinker dummy_data.php
| or inside tinker:   require 'dummy_data.php';
|
| Safe to re-run: it only ADDS data, it does not delete anything.
*/

use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Shift;
use App\Models\EmployeeShift;
use App\Models\ClockEntry;
use Carbon\Carbon;

$companyId = 1;

/* ------------------------------------------------------------------ Company */
Company::where('id', $companyId)->update([
    'name' => 'Bláa Lónið',
    'slug' => 'blaa-lonid',
]);
echo "Company: " . (Company::find($companyId)?->name ?? '(missing!)') . PHP_EOL;

/* ---------------------------------------------------------------- Locations */
$locations = [
    ['name' => 'Bláa Lónið - Grindavík',          'address' => 'Norðurljósavegur 9, 240 Grindavík', 'latitude' => 63.8804, 'longitude' => -22.4495, 'geo_fence_radius' => 250],
    ['name' => 'Retreat Hotel - Grindavík',        'address' => 'Norðurljósavegur 11, 240 Grindavík', 'latitude' => 63.8790, 'longitude' => -22.4520, 'geo_fence_radius' => 200],
    ['name' => 'Bláa Lónið skrifstofa - Reykjavík', 'address' => 'Norðurljósavegur 1, 101 Reykjavík',  'latitude' => 64.1466, 'longitude' => -21.9426, 'geo_fence_radius' => 120],
];

foreach ($locations as $loc) {
    Location::firstOrCreate(
        ['company_id' => $companyId, 'name' => $loc['name']],
        $loc + ['company_id' => $companyId]
    );
}
echo "Locations: " . Location::where('company_id', $companyId)->count() . PHP_EOL;

/* ---------------------------------------------------------------- Employees */
$names = [
    'Jón Þór Sigurðsson',
    'Guðrún Anna Jónsdóttir',
    'Ólafur Ragnar Magnússon',
    'Helga María Kristjánsdóttir',
    'Einar Örn Gunnarsson',
    'Sigríður Halla Þórðardóttir',
    'Kristján Bjarni Ólafsson',
    'Anna Lísa Stefánsdóttir',
    'Bjarki Snær Guðmundsson',
    'Þóra Björk Einarsdóttir',
    'Magnús Karl Pétursson',
    'Elín Rós Halldórsdóttir',
];

$employees = [];
foreach ($names as $i => $name) {
    // Plausible kennitala-style SSN (not validated, just dummy 10 digits)
    $day   = str_pad((string) (1 + ($i * 2) % 28), 2, '0', STR_PAD_LEFT);
    $month = str_pad((string) (1 + $i % 12), 2, '0', STR_PAD_LEFT);
    $year  = str_pad((string) (70 + $i), 2, '0', STR_PAD_LEFT);
    $ssn   = $day . $month . $year . str_pad((string) (1000 + $i * 137 % 9000), 4, '0', STR_PAD_LEFT);

    $first = explode(' ', $name)[0];
    $slug  = strtolower(str_replace(
        ['á','é','í','ó','ú','ý','þ','æ','ö','ð',' '],
        ['a','e','i','o','u','y','th','ae','o','d','.'],
        $first
    ));

    $employees[] = Employee::firstOrCreate(
        ['company_id' => $companyId, 'ssn' => $ssn],
        [
            'company_id' => $companyId,
            'name'       => $name,
            'email'      => $slug . ($i + 1) . '@example.is',
            'phone'      => '+354 ' . (600 + $i) . ' ' . str_pad((string) (1000 + $i * 211 % 9000), 4, '0', STR_PAD_LEFT),
            'is_active'  => true,
        ]
    );
}
echo "Employees: " . Employee::where('company_id', $companyId)->count() . PHP_EOL;

/* ------------------------------------------------------------------- Shifts */
$shiftTypes = [
    ['title' => 'Morgunvakt', 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'notes' => 'Dagvakt í móttöku og spa'],
    ['title' => 'Kvöldvakt',  'start_time' => '15:00:00', 'end_time' => '23:00:00', 'notes' => 'Kvöldvakt í móttöku og spa'],
    ['title' => 'Næturvakt',  'start_time' => '23:00:00', 'end_time' => '07:00:00', 'notes' => 'Næturvakt á hóteli'],
];

$shifts = [];
foreach ($shiftTypes as $st) {
    $shifts[] = Shift::firstOrCreate(
        ['company_id' => $companyId, 'title' => $st['title']],
        $st + ['company_id' => $companyId]
    );
}
echo "Shifts: " . Shift::where('company_id', $companyId)->count() . PHP_EOL;

/* -------------------------------------------------- Assignments (4 weeks) */
// 2 weeks in the past (for clock entries) + 2 weeks ahead.
$start = Carbon::today()->subDays(14);
$assignmentsCreated = 0;
$clockEntriesCreated = 0;

for ($d = 0; $d < 28; $d++) {
    $date = $start->copy()->addDays($d);

    foreach ($shifts as $shiftIndex => $shift) {
        // 2 employees per shift per day, rotating through the roster
        for ($n = 0; $n < 2; $n++) {
            $emp = $employees[($d + $shiftIndex * 2 + $n) % count($employees)];

            $assignment = EmployeeShift::firstOrCreate(
                [
                    'shift_id'    => $shift->id,
                    'employee_id' => $emp->id,
                    'date'        => $date->toDateString(),
                ],
                [
                    'published'             => true,
                    'published_date'        => $date->toDateString(),
                    'published_employee_id' => $emp->id,
                ]
            );
            if ($assignment->wasRecentlyCreated) {
                $assignmentsCreated++;
            }

            // For past dates, create a realistic clock entry
            if ($date->isPast() && ! $date->isToday()) {
                [$sh, $sm] = explode(':', $shift->start_time);
                [$eh, $em] = explode(':', $shift->end_time);

                $clockIn = $date->copy()->setTime((int) $sh, (int) $sm)->subMinutes(rand(0, 8));
                $clockOut = $date->copy()->setTime((int) $eh, (int) $em);
                if ($clockOut->lessThanOrEqualTo($clockIn)) {
                    $clockOut->addDay(); // night shift wraps past midnight
                }
                $clockOut->addMinutes(rand(0, 12));

                $exists = ClockEntry::where('employee_id', $emp->id)
                    ->whereDate('clocked_in_at', $clockIn->toDateString())
                    ->where('shift_id', $shift->id)
                    ->exists();

                if (! $exists) {
                    ClockEntry::create([
                        'shift_id'       => $shift->id,
                        'employee_id'    => $emp->id,
                        'clocked_in_at'  => $clockIn,
                        'clocked_out_at' => $clockOut,
                        'clock_in_lat'   => 63.8804 + (rand(-50, 50) / 100000),
                        'clock_in_lng'   => -22.4495 + (rand(-50, 50) / 100000),
                    ]);
                    $clockEntriesCreated++;
                }
            }
        }
    }
}

echo "Assignments created: {$assignmentsCreated}" . PHP_EOL;
echo "Clock entries created: {$clockEntriesCreated}" . PHP_EOL;
echo "Done ✅" . PHP_EOL;
