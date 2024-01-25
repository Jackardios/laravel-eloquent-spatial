<?php

namespace MatanYadaev\EloquentSpatial\Exceptions;

use InvalidArgumentException;

final class InvalidLatitude extends InvalidArgumentException
{
  public static function make(float $latitude): self
  {
    return new static("Invalid latitude (`$latitude`) passed.");
  }
}
