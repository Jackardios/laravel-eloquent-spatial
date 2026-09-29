<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Brick\Geo\Geometry as BrickGeometry;
use Brick\Geo\GeometryCollection as BrickGeometryCollection;
use Brick\Geo\Io\EwktReader;
use Brick\Geo\Io\GeoJson\Feature;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\LineString as BrickLineString;
use Brick\Geo\MultiLineString as BrickMultiLineString;
use Brick\Geo\MultiPoint as BrickMultiPoint;
use Brick\Geo\MultiPolygon as BrickMultiPolygon;
use Brick\Geo\Point as BrickPoint;
use Brick\Geo\Polygon as BrickPolygon;
use Closure;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Throwable;

class Factory
{
    /**
     * The deepest nesting of geometries that is read, where a geometry that is not in a collection has a depth of 1.
     *
     * Much deeper nesting crashes PHP while brick/geo reads the geometries or PHP frees them.
     */
    private const int MAX_DEPTH = 64;

    /**
     * Parses WKT or EWKT, GeoJSON, or WKB or EWKB (binary or hex).
     *
     * The SRID is read from EWKT and EWKB, and is 0 otherwise.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $value): Geometry
    {
        // Binary WKB always has a byte order of 0 or 1, which text does not have. The first bytes can look like text:
        // in the MySQL format they are the SRID, such as "j" for 2154 or "{" for 32635.
        if (preg_match('/[\x00-\x08]/', $value) === 1) {
            return self::parseWkb($value);
        }

        if (preg_match('/^\s*\{/', $value) === 1) {
            return self::parseJson($value, 0);
        }

        if (preg_match('/^\s*[A-Za-z]/', $value) === 1 && ! ctype_xdigit($value)) {
            return self::parseWkt($value, null, 0);
        }

        return self::parseWkb($value);
    }

    /**
     * @param  int|null  $srid  The SRID of the geometry, or null to read it from EWKT.
     * @param  int  $defaultSrid  The SRID if it is null and the value is not EWKT.
     *
     * @throws InvalidArgumentException
     *
     * @internal
     */
    public static function parseWkt(string $wkt, ?int $srid, int $defaultSrid): Geometry
    {
        self::validateWktDepth($wkt);

        $geometry = self::read(static fn (): BrickGeometry => (new EwktReader)->read($wkt));

        if ($srid === null) {
            $srid = preg_match('/^\s*SRID=/i', $wkt) === 1 ? $geometry->srid() : $defaultSrid;
        }

        return self::create($geometry, $srid);
    }

    /**
     * A Feature is read as its geometry, and a FeatureCollection as its only geometry or as a collection of them.
     *
     * @throws InvalidArgumentException
     *
     * @internal
     */
    public static function parseJson(string $geoJson, int $srid): Geometry
    {
        // Version 4 wrote Features with "properties": [], which brick/geo rejects even in lenient mode. The properties
        // are not read, and the pattern cannot match inside a JSON string, whose quotes are escaped.
        $geoJson = preg_replace('/"properties"\s*:\s*\[\s*\]/', '"properties":{}', $geoJson) ?? $geoJson;

        $object = self::read(static fn () => (new GeoJsonReader(lenient: true))->read($geoJson));

        if ($object instanceof BrickGeometry) {
            return self::create($object, $srid);
        }

        $features = array_values($object instanceof Feature ? [$object] : $object->getFeatures());

        // Several geometries are put in a collection, so they are one level deeper.
        $depth = count($features) > 1 ? 2 : 1;

        $geometries = array_map(static function (Feature $feature) use ($srid, $depth): Geometry {
            $geometry = $feature->getGeometry();

            if ($geometry === null) {
                throw new InvalidArgumentException('Invalid spatial value: a GeoJSON Feature has no geometry.');
            }

            return self::create($geometry, $srid, $depth);
        }, $features);

        return match (count($geometries)) {
            0 => throw new InvalidArgumentException('Invalid spatial value: the GeoJSON FeatureCollection has no features.'),
            1 => $geometries[0],
            default => self::combine($geometries, $srid),
        };
    }

    /**
     * As in version 4, points, lines or polygons, single or multi, are merged into one multi geometry. Other
     * geometries are kept apart in a GeometryCollection.
     *
     * @param  list<Geometry>  $geometries
     *
     * @throws InvalidArgumentException
     */
    private static function combine(array $geometries, int $srid): Geometry
    {
        $points = [];
        $lineStrings = [];
        $polygons = [];

        foreach ($geometries as $geometry) {
            // Polygon extends MultiLineString, so a multi geometry is recognized by its type.
            $parts = in_array(Helper::geometryType($geometry), ['MultiPoint', 'MultiLineString', 'MultiPolygon'], true)
                && $geometry instanceof GeometryCollection ? $geometry->getGeometries()->all() : [$geometry];

            foreach ($parts as $part) {
                if ($part instanceof Point) {
                    $points[] = $part;
                } elseif ($part instanceof LineString) {
                    $lineStrings[] = $part;
                } elseif ($part instanceof Polygon) {
                    $polygons[] = $part;
                } else {
                    return new EloquentSpatial::$geometryCollection($geometries, $srid);
                }
            }
        }

        return match (true) {
            $lineStrings === [] && $polygons === [] => new EloquentSpatial::$multiPoint($points, $srid),
            $points === [] && $polygons === [] => new EloquentSpatial::$multiLineString($lineStrings, $srid),
            $points === [] && $lineStrings === [] => new EloquentSpatial::$multiPolygon($polygons, $srid),
            default => new EloquentSpatial::$geometryCollection($geometries, $srid),
        };
    }

    /**
     * Parses the WKB that MySQL stores (a 4-byte SRID followed by WKB), or WKB or EWKB, binary or hex.
     *
     * The SRID is read from the MySQL format or EWKB, and is 0 otherwise.
     *
     * @throws InvalidArgumentException
     *
     * @internal
     */
    public static function parseWkb(string $wkb): Geometry
    {
        $isHex = $wkb !== '' && ctype_xdigit($wkb);

        if ($isHex) {
            $binary = strlen($wkb) % 2 === 0 ? hex2bin($wkb) : false;

            if ($binary === false) {
                throw new InvalidArgumentException('Invalid spatial value: the hex WKB has an odd length.');
            }

            $wkb = $binary;
        }

        // The format that the value most likely has is tried first: PostGIS returns hex EWKB, MySQL returns binary.
        try {
            return self::readWkb($wkb, hasSridPrefix: ! $isHex);
        } catch (InvalidArgumentException $exception) {
            try {
                return self::readWkb($wkb, hasSridPrefix: $isHex);
            } catch (InvalidArgumentException) {
                throw $exception;
            }
        }
    }

    /**
     * @param  bool  $hasSridPrefix  Whether the value is in the MySQL format, a 4-byte SRID followed by WKB.
     *
     * @throws InvalidArgumentException
     */
    private static function readWkb(string $value, bool $hasSridPrefix): Geometry
    {
        $srid = null;

        if ($hasSridPrefix) {
            if (strlen($value) < 4) {
                throw new InvalidArgumentException('Invalid spatial value: the WKB is too short.');
            }

            /** @var array{1: int} $unpacked */
            $unpacked = unpack('V', $value);
            $srid = $unpacked[1];
            $value = substr($value, 4);
        }

        return Wkb::read($value, $srid, self::MAX_DEPTH);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     *
     * @throws InvalidArgumentException
     */
    private static function read(Closure $read): mixed
    {
        try {
            return $read();
        } catch (Throwable $exception) {
            // Besides its own GeometryException, brick/geo throws a TypeError, for example for GeoJSON coordinates that are strings.
            throw new InvalidArgumentException('Invalid spatial value: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Brick/geo reads WKT recursively, so the nesting is limited before it is read.
     *
     * A geometry at the maximum depth can still have two more levels of parentheses, as a MultiPolygon has.
     *
     * @throws InvalidArgumentException
     */
    private static function validateWktDepth(string $wkt): void
    {
        $depth = 0;
        $length = strlen($wkt);

        for ($offset = strcspn($wkt, '()'); $offset < $length; $offset += 1 + strcspn($wkt, '()', $offset + 1)) {
            $depth += $wkt[$offset] === '(' ? 1 : -1;

            // PHPStan does not widen $depth over the iterations of this loop.
            // @phpstan-ignore greater.alwaysFalse
            if ($depth > self::MAX_DEPTH + 2) {
                throw Wkb::tooDeep(self::MAX_DEPTH);
            }
        }
    }

    /**
     * Z and M coordinates are dropped.
     *
     * @throws InvalidArgumentException
     */
    private static function create(BrickGeometry $geometry, int $srid, int $depth = 1): Geometry
    {
        $type = $geometry->geometryType();

        // The type is checked too, because some brick/geo classes extend others, such as Triangle extends Polygon.
        return match (true) {
            $geometry instanceof BrickPoint && $type === 'Point' => self::createPoint($geometry, $srid),
            $geometry instanceof BrickLineString && $type === 'LineString' => self::createLineString($geometry, $srid),
            $geometry instanceof BrickPolygon && $type === 'Polygon' => self::createPolygon($geometry, $srid),
            $geometry instanceof BrickMultiPoint && $type === 'MultiPoint' => new EloquentSpatial::$multiPoint(
                self::createChildren($geometry->geometries(), $depth, static fn (BrickPoint $point): Point => self::createPoint($point, $srid)),
                $srid,
            ),
            $geometry instanceof BrickMultiLineString && $type === 'MultiLineString' => new EloquentSpatial::$multiLineString(
                self::createChildren($geometry->geometries(), $depth, static fn (BrickLineString $lineString): LineString => self::createLineString($lineString, $srid)),
                $srid,
            ),
            $geometry instanceof BrickMultiPolygon && $type === 'MultiPolygon' => new EloquentSpatial::$multiPolygon(
                self::createChildren($geometry->geometries(), $depth, static fn (BrickPolygon $polygon): Polygon => self::createPolygon($polygon, $srid)),
                $srid,
            ),
            $geometry instanceof BrickGeometryCollection && $type === 'GeometryCollection' => new EloquentSpatial::$geometryCollection(
                self::createChildren($geometry->geometries(), $depth, static fn (BrickGeometry $child): Geometry => self::create($child, $srid, $depth + 1)),
                $srid,
            ),
            default => throw new InvalidArgumentException("Invalid spatial value: {$type} geometries are not supported."),
        };
    }

    /**
     * The geometries in a collection are one level deeper, as in WKB, where each of them has its own header.
     *
     * @template TBrickGeometry of BrickGeometry
     * @template TGeometry of Geometry
     *
     * @param  list<TBrickGeometry>  $children
     * @param  Closure(TBrickGeometry): TGeometry  $create
     * @return list<TGeometry>
     *
     * @throws InvalidArgumentException
     */
    private static function createChildren(array $children, int $depth, Closure $create): array
    {
        if ($children !== [] && $depth >= self::MAX_DEPTH) {
            throw Wkb::tooDeep(self::MAX_DEPTH);
        }

        return array_map($create, $children);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function createLineString(BrickLineString $lineString, int $srid): LineString
    {
        return new EloquentSpatial::$lineString(
            array_map(static fn (BrickPoint $point): Point => self::createPoint($point, $srid), $lineString->points()),
            $srid,
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function createPolygon(BrickPolygon $polygon, int $srid): Polygon
    {
        return new EloquentSpatial::$polygon(
            array_map(static fn (BrickLineString $ring): LineString => self::createLineString($ring, $srid), $polygon->rings()),
            $srid,
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function createPoint(BrickPoint $point, int $srid): Point
    {
        $longitude = $point->x();
        $latitude = $point->y();

        if ($longitude === null || $latitude === null) {
            throw new InvalidArgumentException('Invalid spatial value: empty points are not supported.');
        }

        return new EloquentSpatial::$point($longitude, $latitude, $srid);
    }
}
