<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use InvalidArgumentException;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiLineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

/**
 * Writes 2D little-endian WKB (ISO/OGC).
 *
 * @internal
 */
final class Wkb
{
    private const int LITTLE_ENDIAN = 1;

    public static function write(Geometry $geometry): string
    {
        // Polygon extends MultiLineString and LineString extends PointCollection, so the order matters.
        return match (true) {
            $geometry instanceof Point => self::header(1).self::coordinates($geometry),
            $geometry instanceof LineString => self::header(2).self::points($geometry),
            $geometry instanceof MultiPoint => self::collection(4, $geometry),
            $geometry instanceof Polygon => self::header(3).self::rings($geometry),
            $geometry instanceof MultiLineString => self::collection(5, $geometry),
            $geometry instanceof MultiPolygon => self::collection(6, $geometry),
            $geometry instanceof GeometryCollection => self::collection(7, $geometry),
            default => throw new InvalidArgumentException(sprintf('Cannot write %s as WKB.', $geometry::class)),
        };
    }

    private static function header(int $type): string
    {
        return pack('CV', self::LITTLE_ENDIAN, $type);
    }

    private static function coordinates(Point $point): string
    {
        return pack('ee', $point->longitude, $point->latitude);
    }

    private static function points(LineString $lineString): string
    {
        $wkb = pack('V', count($lineString->getGeometries()));

        foreach ($lineString->getGeometries() as $point) {
            $wkb .= self::coordinates($point);
        }

        return $wkb;
    }

    private static function rings(Polygon $polygon): string
    {
        $wkb = pack('V', count($polygon->getGeometries()));

        foreach ($polygon->getGeometries() as $ring) {
            $wkb .= self::points($ring);
        }

        return $wkb;
    }

    private static function collection(int $type, GeometryCollection $collection): string
    {
        $wkb = self::header($type).pack('V', count($collection->getGeometries()));

        foreach ($collection->getGeometries() as $geometry) {
            $wkb .= self::write($geometry);
        }

        return $wkb;
    }
}
