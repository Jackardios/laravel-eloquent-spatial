<?php

namespace MatanYadaev\EloquentSpatial\Tests\TestModels;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MatanYadaev\EloquentSpatial\BoundingBox;
use MatanYadaev\EloquentSpatial\Objects\GeometryCollection;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\MultiLineString;
use MatanYadaev\EloquentSpatial\Objects\MultiPoint;
use MatanYadaev\EloquentSpatial\Objects\MultiPolygon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\SpatialBuilder;
use MatanYadaev\EloquentSpatial\Tests\TestFactories\TestPlaceFactory;

/**
 * @property Point|null $point
 * @property MultiPoint|null $multi_point
 * @property LineString|null $line_string
 * @property MultiLineString|null $multi_line_string
 * @property Polygon|null $polygon
 * @property MultiPolygon|null $multi_polygon
 * @property GeometryCollection|null $geometry_collection
 * @property BoundingBox|null $bounding_box
 * @property float|null $distance
 * @property float|null $distance_in_meters
 * @mixin Model
 * @method static SpatialBuilder query()
 */
class TestPlace extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'point',
        'multi_point',
        'line_string',
        'multi_line_string',
        'polygon',
        'multi_polygon',
        'geometry_collection',
        'point_with_line_string_cast',
        'bounding_box',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $casts = [
        'point' => Point::class,
        'multi_point' => MultiPoint::class,
        'line_string' => LineString::class,
        'multi_line_string' => MultiLineString::class,
        'polygon' => Polygon::class,
        'multi_polygon' => MultiPolygon::class,
        'geometry_collection' => GeometryCollection::class,
        'point_with_line_string_cast' => LineString::class,
        'bounding_box' => BoundingBox::class,
    ];

    public function newEloquentBuilder($query): SpatialBuilder
    {
        return new SpatialBuilder($query);
    }

    protected static function newFactory(): TestPlaceFactory
    {
        return new TestPlaceFactory;
    }
}
