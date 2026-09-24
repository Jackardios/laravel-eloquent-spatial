<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial\Objects;

use InvalidArgumentException;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Helper;

class Point extends Geometry
{
    /**
     * SRIDs whose coordinates are validated as longitude and latitude in degrees.
     */
    private const array LONGITUDE_LATITUDE_SRIDS = [0, 4326];

    public float $longitude;

    public float $latitude;

    public function __construct(float $longitude, float $latitude, int|Srid|null $srid = null)
    {
        if (! is_finite($longitude) || ! is_finite($latitude)) {
            throw new InvalidArgumentException(sprintf(
                'Coordinates must be finite numbers, got: %s %s',
                var_export($longitude, true),
                var_export($latitude, true),
            ));
        }

        $srid = Helper::getSrid($srid);

        // Other SRIDs, e.g. projected ones such as Web Mercator, use their own units and ranges.
        if (in_array($srid, self::LONGITUDE_LATITUDE_SRIDS, true)) {
            if ($latitude < -90 || $latitude > 90) {
                throw new InvalidArgumentException("Latitude must be between -90 and 90, got: $latitude");
            }
            if ($longitude < -180 || $longitude > 180) {
                throw new InvalidArgumentException("Longitude must be between -180 and 180, got: $longitude");
            }
        }

        $this->longitude = $longitude;
        $this->latitude = $latitude;
        $this->srid = $srid;
    }

    public function toWkt(): string
    {
        $wktData = $this->getWktData();

        return "POINT({$wktData})";
    }

    public function getWktData(): string
    {
        return "{$this->longitude} {$this->latitude}";
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function getCoordinates(): array
    {
        return [
            $this->longitude,
            $this->latitude,
        ];
    }
}
