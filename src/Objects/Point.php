<?php

declare(strict_types=1);

namespace MatanYadaev\EloquentSpatial\Objects;

use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidLatitude;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidLongitude;

class Point extends Geometry
{
  public float $longitude;

  public float $latitude;

  public function __construct(float $longitude, float $latitude, int|Srid $srid = 0)
  {
    $this->validateCoordinates($longitude, $latitude);
    $this->longitude = $longitude;
    $this->latitude = $latitude;
    $this->srid = $srid instanceof Srid ? $srid->value : $srid;
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

  public function getLongitude(): float
  {
    return $this->longitude;
  }

  public function getLatitude(): float
  {
    return $this->latitude;
  }

  protected function validateCoordinates(float $longitude, float $latitude): void
  {
    if ($longitude < -180 || $longitude > 180) {
      throw InvalidLongitude::make($longitude);
    }

    if ($latitude < -90 || $latitude > 90) {
      throw InvalidLatitude::make($latitude);
    }
  }
}
