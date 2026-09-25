<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use InvalidArgumentException;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiLineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

class Helper
{
    public static function getSrid(Srid|int|null $srid = null): int
    {
        if ($srid instanceof Srid) {
            return $srid->value;
        }

        if (is_int($srid)) {
            return $srid;
        }

        return EloquentSpatial::$defaultSrid;
    }

    /**
     * The type of a geometry or geometry class, such as "MultiPoint" for a subclass of MultiPoint, or null for a class
     * that is not one of the types.
     *
     * @param  Geometry|class-string<Geometry>  $geometry
     *
     * @internal
     */
    public static function geometryType(Geometry|string $geometry): ?string
    {
        // Polygon extends MultiLineString and LineString extends PointCollection, so the order matters.
        return match (true) {
            is_a($geometry, Point::class, true) => 'Point',
            is_a($geometry, LineString::class, true) => 'LineString',
            is_a($geometry, MultiPoint::class, true) => 'MultiPoint',
            is_a($geometry, Polygon::class, true) => 'Polygon',
            is_a($geometry, MultiLineString::class, true) => 'MultiLineString',
            is_a($geometry, MultiPolygon::class, true) => 'MultiPolygon',
            is_a($geometry, GeometryCollection::class, true) => 'GeometryCollection',
            default => null,
        };
    }

    /**
     * Parse ST_GeomFromText SQL expression and extract WKT and SRID.
     *
     * @return array{wkt: string, srid: int}
     *
     * @throws InvalidArgumentException
     */
    public static function parseStGeomFromText(string $expressionValue): array
    {
        $result = preg_match(
            "/ST_GeomFromText\(\s*'([^']+)'\s*(?:,\s*(\d+))?\s*(?:,\s*'([^']+)')?\s*\)/",
            $expressionValue,
            $matches
        );

        if ($result !== 1) {
            throw new InvalidArgumentException('Unable to parse ST_GeomFromText expression: '.$expressionValue);
        }

        return [
            'wkt' => $matches[1],
            'srid' => (int) ($matches[2] ?? 0),
        ];
    }
}
