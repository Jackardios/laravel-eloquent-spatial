<?php

namespace Jackardios\EloquentSpatial\Tests\TestModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jackardios\EloquentSpatial\Tests\TestFactories\TestExtendedPlaceFactory;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedGeometryCollection;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedLineString;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiLineString;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiPoint;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedMultiPolygon;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedPoint;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedPolygon;
use Jackardios\EloquentSpatial\Traits\HasSpatial;

/**
 * @property ExtendedGeometryCollection $geometry_collection
 * @property ExtendedPoint $point
 * @property ExtendedMultiPoint $multi_point
 * @property ExtendedLineString $line_string
 * @property ExtendedMultiLineString $multi_line_string
 * @property ExtendedPolygon $polygon
 * @property ExtendedMultiPolygon $multi_polygon
 *
 * @mixin Model
 */
class TestExtendedPlace extends Model
{
    use HasFactory;
    use HasSpatial;

    protected $casts = [
        'geometry_collection' => ExtendedGeometryCollection::class,
        'line_string' => ExtendedLineString::class,
        'multi_line_string' => ExtendedMultiLineString::class,
        'multi_point' => ExtendedMultiPoint::class,
        'multi_polygon' => ExtendedMultiPolygon::class,
        'point' => ExtendedPoint::class,
        'polygon' => ExtendedPolygon::class,
    ];

    protected static function newFactory(): TestExtendedPlaceFactory
    {
        return TestExtendedPlaceFactory::new();
    }
}
