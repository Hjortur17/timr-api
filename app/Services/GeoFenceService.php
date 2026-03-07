<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Validation\ValidationException;

class GeoFenceService
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    public function isWithinRange(float $lat, float $lng, Location $location): bool
    {
        $distance = $this->haversineDistance($lat, $lng, $location->latitude, $location->longitude);

        return $distance <= $location->geo_fence_radius;
    }

    /**
     * @throws ValidationException
     */
    public function validateWithinRange(float $lat, float $lng, Location $location): void
    {
        if (! $this->isWithinRange($lat, $lng, $location)) {
            throw ValidationException::withMessages([
                'location' => 'You are outside the allowed geo-fence radius for this location.',
            ]);
        }
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
