<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Geometry as geoPHPGeometry;
use GeometryCollection as geoPHPGeometryCollection;
use geoPHP;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\Objects\Geometry;
use LineString as geoPHPLineString;
use MultiLineString as geoPHPMultiLineString;
use MultiPoint as geoPHPMultiPoint;
use MultiPolygon as geoPHPMultiPolygon;
use Point as geoPHPPoint;
use Polygon as geoPHPPolygon;
use Throwable;

class Factory
{
    public static function parse(string $value): Geometry
    {
        try {
            /** @var geoPHPGeometry|false $geoPHPGeometry */
            $geoPHPGeometry = geoPHP::load($value);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Invalid spatial value: '.$e->getMessage(), 0, $e);
        }

        if ($geoPHPGeometry === false) {
            throw new InvalidArgumentException('Invalid spatial value');
        }

        return self::createFromGeometry($geoPHPGeometry);
    }

    protected static function createFromGeometry(geoPHPGeometry $geometry): Geometry
    {
        $srid = is_int($geometry->getSRID()) ? $geometry->getSRID() : 0;

        if ($geometry instanceof geoPHPPoint) {
            if ($geometry->coords[0] === null || $geometry->coords[1] === null) {
                throw new InvalidArgumentException('Invalid spatial value');
            }

            return new EloquentSpatial::$point($geometry->coords[0], $geometry->coords[1], $srid);
        }

        /** @var geoPHPGeometryCollection $geometry */
        $components = array_map(
            static fn (geoPHPGeometry $component): Geometry => self::createFromGeometry($component),
            $geometry->components
        );

        $geometryClass = $geometry::class;

        if ($geometryClass === geoPHPMultiPoint::class) {
            return new EloquentSpatial::$multiPoint($components, $srid);
        } elseif ($geometryClass === geoPHPLineString::class) {
            return new EloquentSpatial::$lineString($components, $srid);
        } elseif ($geometryClass === geoPHPPolygon::class) {
            return new EloquentSpatial::$polygon($components, $srid);
        } elseif ($geometryClass === geoPHPMultiLineString::class) {
            return new EloquentSpatial::$multiLineString($components, $srid);
        } elseif ($geometryClass === geoPHPMultiPolygon::class) {
            return new EloquentSpatial::$multiPolygon($components, $srid);
        }

        return new EloquentSpatial::$geometryCollection($components, $srid);
    }
}
