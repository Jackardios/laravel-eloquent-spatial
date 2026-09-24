<?php

use Jackardios\EloquentSpatial\EloquentSpatial;

it('declares SRID 0 as the default SRID', function (): void {
    // TestCase resets the SRID before each test, so check the declared default.
    expect((new ReflectionProperty(EloquentSpatial::class, 'defaultSrid'))->getDefaultValue())->toBe(0);
});
