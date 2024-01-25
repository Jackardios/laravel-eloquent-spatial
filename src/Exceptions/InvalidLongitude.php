<?php

namespace MatanYadaev\EloquentSpatial\Exceptions;

use InvalidArgumentException;

final class InvalidLongitude extends InvalidArgumentException
{
  public static function make(float $longitude): self
  {
    return new static("Invalid longitude (`$longitude`) passed.");
  }
}
