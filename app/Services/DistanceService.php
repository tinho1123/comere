<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DistanceService
{
    /**
     * Calculate distance between two coordinates using the Haversine formula.
     * Returns distance in kilometers.
     */
    public function calculate(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Get the applicable delivery fee for a given distance.
     * Returns null if outside all ranges or no ranges configured.
     */
    public function getFeeForDistance(float $distanceKm, Collection $ranges): ?float
    {
        $applicable = $ranges
            ->where('is_active', true)
            ->sortBy('max_km')
            ->first(fn ($range) => $distanceKm <= $range->max_km);

        return $applicable ? (float) $applicable->fee : null;
    }

    /**
     * Check if coordinates are available for both points.
     */
    public function canCalculate(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): bool
    {
        return $lat1 !== null && $lng1 !== null && $lat2 !== null && $lng2 !== null;
    }
}
