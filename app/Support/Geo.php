<?php

namespace App\Support;

class Geo
{
    /** Great-circle distance in metres. */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = $p2 - $p1;
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /** Length in metres of a GeoJSON-style path [[lng,lat],...]. */
    public static function pathLength(array $coords): float
    {
        $len = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $len += self::distance($coords[$i - 1][1], $coords[$i - 1][0], $coords[$i][1], $coords[$i][0]);
        }

        return $len;
    }

    /**
     * Walk along a GeoJSON-style path [[lng,lat],...] and return a point every $step metres
     * as [[lat,lng],...] (first and last point always included).
     */
    public static function sample(array $coords, float $step = 50.0): array
    {
        if (count($coords) === 0) {
            return [];
        }
        $pts = [[$coords[0][1], $coords[0][0]]];
        $carry = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $a = $coords[$i - 1];
            $b = $coords[$i];
            $seg = self::distance($a[1], $a[0], $b[1], $b[0]);
            if ($seg <= 0) {
                continue;
            }
            $pos = $step - $carry;
            while ($pos <= $seg) {
                $t = $pos / $seg;
                $pts[] = [$a[1] + ($b[1] - $a[1]) * $t, $a[0] + ($b[0] - $a[0]) * $t];
                $pos += $step;
            }
            $carry = $seg - ($pos - $step);
        }
        $last = end($coords);
        $pts[] = [$last[1], $last[0]];

        return $pts;
    }

    /** Smallest distance (m) from a point to any of the sampled points [[lat,lng],...]. */
    public static function minDistance(float $lat, float $lng, array $points): float
    {
        $min = INF;
        foreach ($points as $p) {
            // cheap rejection: 0.01 degree is about 1.1 km
            if (abs($p[0] - $lat) > 0.01 || abs($p[1] - $lng) > 0.01) {
                continue;
            }
            $d = self::distance($lat, $lng, $p[0], $p[1]);
            if ($d < $min) {
                $min = $d;
            }
        }

        return $min;
    }

    /** Bounding box [minLat, maxLat, minLng, maxLng] of GeoJSON-style coords with a margin in degrees. */
    public static function bbox(array $coords, float $margin = 0.003): array
    {
        $lats = array_column($coords, 1);
        $lngs = array_column($coords, 0);

        return [min($lats) - $margin, max($lats) + $margin, min($lngs) - $margin, max($lngs) + $margin];
    }

    /** Convert Bangla digits to ASCII digits. */
    public static function asciiDigits(string $s): string
    {
        return strtr($s, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }
}
