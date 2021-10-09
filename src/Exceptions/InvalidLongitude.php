<?php

namespace MatanYadaev\EloquentSpatial\Exceptions;

use InvalidArgumentException;

class InvalidLongitude extends InvalidArgumentException
{
    public static function make(float $longitude): InvalidLongitude
    {
        return new static("Invalid longitude (`$longitude`) passed.");
    }
}
