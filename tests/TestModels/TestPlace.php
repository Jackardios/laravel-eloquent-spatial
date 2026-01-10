<?php

namespace Jackardios\EloquentSpatial\Tests\TestModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiLineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestFactories\TestPlaceFactory;
use Jackardios\EloquentSpatial\Traits\HasSpatial;

/**
 * @property Geometry $geometry
 * @property Point $point
 * @property MultiPoint $multi_point
 * @property LineString $line_string
 * @property MultiLineString $multi_line_string
 * @property Polygon $polygon
 * @property MultiPolygon $multi_polygon
 * @property GeometryCollection $geometry_collection
 * @property BoundingBox $bounding_box
 * @property BoundingBox $bounding_box_json
 * @property float|null $distance
 * @property float|null $distance_in_meters
 * @property Point|null $centroid
 * @property Point|null $centroid_alias
 *
 * @mixin Model
 */
class TestPlace extends Model
{
    use HasFactory, HasSpatial;

    protected $fillable = [
        'address',
        'geometry',
        'point',
        'multi_point',
        'line_string',
        'multi_line_string',
        'polygon',
        'multi_polygon',
        'geometry_collection',
        'point_with_line_string_cast',
        'bounding_box',
        'bounding_box_json',
    ];

    protected $casts = [
        'geometry' => Geometry::class,
        'point' => Point::class,
        'multi_point' => MultiPoint::class,
        'line_string' => LineString::class,
        'multi_line_string' => MultiLineString::class,
        'polygon' => Polygon::class,
        'multi_polygon' => MultiPolygon::class,
        'geometry_collection' => GeometryCollection::class,
        'point_with_line_string_cast' => LineString::class,
        'bounding_box' => BoundingBox::class,
        'bounding_box_json' => BoundingBox::class.':json',
        'distance' => 'float',
        'distance_in_meters' => 'float',
    ];

    protected static function newFactory(): TestPlaceFactory
    {
        return new TestPlaceFactory;
    }
}
