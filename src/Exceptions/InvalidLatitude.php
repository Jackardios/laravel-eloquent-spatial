<?php

namespace MatanYadaev\EloquentSpatial\Exceptions;

use InvalidArgumentException;

class InvalidLatitude extends InvalidArgumentException
{
    public static function make(float $latitude): InvalidLatitude
    {
        return new static("Invalid latitude (`$latitude`) passed.");
    }
}
