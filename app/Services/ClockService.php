<?php

namespace App\Services;

use App\Models\ClockEntry;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClockService
{
    public function __construct(private GeoFenceService $geoFenceService) {}

    /**
     * @throws ValidationException
     */
    public function clockIn(User $user, Shift $shift, float $latitude, float $longitude): ClockEntry
    {
        $existingEntry = ClockEntry::query()
            ->where('shift_id', $shift->id)
            ->where('user_id', $user->id)
            ->whereNull('clocked_out_at')
            ->first();

        if ($existingEntry) {
            throw ValidationException::withMessages([
                'shift_id' => 'You are already clocked in for this shift.',
            ]);
        }

        $location = Location::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $user->company_id)
            ->first();

        if ($location) {
            $this->geoFenceService->validateWithinRange($latitude, $longitude, $location);
        }

        return ClockEntry::create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'clocked_in_at' => now(),
            'clock_in_lat' => $latitude,
            'clock_in_lng' => $longitude,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function clockOut(User $user): ClockEntry
    {
        $entry = ClockEntry::query()
            ->where('user_id', $user->id)
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
