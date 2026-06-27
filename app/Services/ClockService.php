<?php

namespace App\Services;

use App\Models\ClockEntry;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Shift;
use Illuminate\Validation\ValidationException;

class ClockService
{
    public function __construct(private GeoFenceService $geoFenceService) {}

    /**
     * @throws ValidationException
     */
    public function clockIn(Employee $employee, ?Shift $shift, float $latitude, float $longitude): ClockEntry
    {
        $existingQuery = ClockEntry::query()
            ->where('employee_id', $employee->id)
            ->whereNull('clocked_out_at');

        if ($shift) {
            $existingQuery->where('shift_id', $shift->id);
        } else {
            $existingQuery->whereNull('shift_id');
        }

        if ($existingQuery->exists()) {
            throw ValidationException::withMessages([
                'shift_id' => $shift
                    ? 'You are already clocked in for this shift.'
                    : 'You are already clocked in.',
            ]);
        }

        // Geo-fence against the shift's own workplace. For an extra clock-in (no
        // shift) or a shift with no workplace, fall back to the nearest workplace.
        $location = $shift?->location ?? $this->geoFenceService->nearest(
            $latitude,
            $longitude,
            Location::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $employee->company_id)
                ->get(),
        );

        // Only enforce when the workplace has GPS configured; a workplace with GPS
        // off (null radius/coords) records the location but imposes no fence.
        if ($location && $location->geo_fence_radius !== null
            && $location->latitude !== null && $location->longitude !== null) {
            $this->geoFenceService->validateWithinRange($latitude, $longitude, $location);
        }

        return ClockEntry::create([
            'shift_id' => $shift?->id,
            'location_id' => $location?->id,
            'employee_id' => $employee->id,
            'clocked_in_at' => now(),
            'clock_in_lat' => $latitude,
            'clock_in_lng' => $longitude,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function clockOut(Employee $employee): ClockEntry
    {
        $entry = ClockEntry::query()
            ->where('employee_id', $employee->id)
            ->whereNull('clocked_out_at')
            ->latest('clocked_in_at')
            ->first();

        if (! $entry) {
            throw ValidationException::withMessages([
                'clock' => 'You are not currently clocked in.',
            ]);
        }

        $entry->update(['clocked_out_at' => now()]);

        return $entry->fresh();
    }
}
