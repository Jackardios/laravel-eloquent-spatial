<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial\Objects;

use InvalidArgumentException;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Helper;

class Point extends Geometry
{
    public float $longitude;

    public float $latitude;

    public function __construct(float $longitude, float $latitude, int|Srid|null $srid = null)
    {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException("Latitude must be between -90 and 90, got: $latitude");
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException("Longitude must be between -180 and 180, got: $longitude");
        }

        $this->longitude = $longitude;
        $this->latitude = $latitude;
        $this->srid = Helper::getSrid($srid);
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
