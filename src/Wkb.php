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
 * Writes 2D little-endian WKB, and reads WKB and EWKB.
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

    /**
     * Reads WKB or EWKB. Z and M coordinates are dropped.
     *
     * @param  int|null  $srid  The SRID of the geometry, or null for the SRID of the EWKB, which is 0 for WKB.
     *
     * @throws InvalidArgumentException
     */
    public static function read(string $wkb, ?int $srid, int $maxDepth): Geometry
    {
        $offset = 0;
        $geometry = self::readGeometry($wkb, $offset, $srid, 1, $maxDepth);

        if ($offset !== strlen($wkb)) {
            throw new InvalidArgumentException('Invalid spatial value: unexpected data after the WKB geometry.');
        }

        return $geometry;
    }

    public static function tooDeep(int $maxDepth): InvalidArgumentException
    {
        return new InvalidArgumentException(
            sprintf('Invalid spatial value: geometries nested deeper than %d levels are not supported.', $maxDepth)
        );
    }

    /**
     * @return array{int, int, bool} The geometry type, the number of dimensions, and whether an SRID follows.
     */
    private static function parseHeader(int $header): array
    {
        if ($header < 4000) {
            // ISO WKB: 1000 is added to the type for Z, 2000 for M and 3000 for both.
            return [$header % 1000, 2 + [0, 1, 1, 2][intdiv($header, 1000)], false];
        }

        // EWKB: flags in the high bits.
        return [
            $header & 0x0FFFFFFF,
            2 + ($header & 0x80000000 ? 1 : 0) + ($header & 0x40000000 ? 1 : 0),
            ($header & 0x20000000) !== 0,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function readGeometry(string $wkb, int &$offset, ?int $srid, int $depth, int $maxDepth): Geometry
    {
        if ($depth > $maxDepth) {
            throw self::tooDeep($maxDepth);
        }

        $byteOrder = ord(self::take($wkb, $offset, 1));

        if ($byteOrder > 1) {
            throw new InvalidArgumentException("Invalid spatial value: invalid WKB byte order {$byteOrder}.");
        }

        $format = $byteOrder === self::LITTLE_ENDIAN ? 'V' : 'N';
        [$type, $dimensions, $hasSrid] = self::parseHeader(self::readInteger($wkb, $offset, $format));

        if ($hasSrid) {
            // The SRID of the outermost geometry applies to the geometries in it.
            $ewkbSrid = self::readInteger($wkb, $offset, $format);
            $srid ??= $ewkbSrid;
        }

        $srid ??= 0;

        if ($type === 1) {
            self::checkLength($wkb, $offset, 8 * $dimensions);
        }

        $pointFormat = $format === 'V' ? 'e2' : 'E2';

        return match ($type) {
            1 => self::readPoint($wkb, $offset, $pointFormat, $dimensions, $srid),
            2 => new EloquentSpatial::$lineString(self::readPoints($wkb, $offset, $format, $pointFormat, $dimensions, $srid), $srid),
            3 => new EloquentSpatial::$polygon(self::readRings($wkb, $offset, $format, $pointFormat, $dimensions, $srid), $srid),
            4 => new EloquentSpatial::$multiPoint(self::readGeometries($wkb, $offset, $format, $srid, $depth, $maxDepth, Point::class), $srid),
            5 => new EloquentSpatial::$multiLineString(self::readGeometries($wkb, $offset, $format, $srid, $depth, $maxDepth, LineString::class), $srid),
            6 => new EloquentSpatial::$multiPolygon(self::readGeometries($wkb, $offset, $format, $srid, $depth, $maxDepth, Polygon::class), $srid),
            7 => new EloquentSpatial::$geometryCollection(self::readGeometries($wkb, $offset, $format, $srid, $depth, $maxDepth, Geometry::class), $srid),
            default => throw new InvalidArgumentException("Invalid spatial value: unsupported WKB geometry type {$type}."),
        };
    }

    /**
     * Reads the coordinates without a bounds check: the caller checks the length of all points of a line at once,
     * which makes reading a large geometry 20% faster than take() for every point.
     *
     * @throws InvalidArgumentException
     */
    private static function readPoint(string $wkb, int &$offset, string $pointFormat, int $dimensions, int $srid): Point
    {
        /** @var array{1: float, 2: float} $coordinates */
        $coordinates = unpack($pointFormat, $wkb, $offset);
        $offset += 8 * $dimensions;

        // PostGIS writes an empty point as NaN coordinates.
        if (is_nan($coordinates[1]) && is_nan($coordinates[2])) {
            throw new InvalidArgumentException('Invalid spatial value: empty points are not supported.');
        }

        return new EloquentSpatial::$point($coordinates[1], $coordinates[2], $srid);
    }

    /**
     * @return list<Point>
     *
     * @throws InvalidArgumentException
     */
    private static function readPoints(string $wkb, int &$offset, string $format, string $pointFormat, int $dimensions, int $srid): array
    {
        $count = self::readInteger($wkb, $offset, $format);
        self::checkLength($wkb, $offset, 8 * $dimensions * $count);
        $points = [];

        for (; $count > 0; $count--) {
            $points[] = self::readPoint($wkb, $offset, $pointFormat, $dimensions, $srid);
        }

        return $points;
    }

    /**
     * @return list<LineString>
     *
     * @throws InvalidArgumentException
     */
    private static function readRings(string $wkb, int &$offset, string $format, string $pointFormat, int $dimensions, int $srid): array
    {
        $rings = [];

        for ($count = self::readInteger($wkb, $offset, $format); $count > 0; $count--) {
            $rings[] = new EloquentSpatial::$lineString(self::readPoints($wkb, $offset, $format, $pointFormat, $dimensions, $srid), $srid);
        }

        return $rings;
    }

    /**
     * @template T of Geometry
     *
     * @param  class-string<T>  $class  The class of the geometries, which WKB does not ensure.
     * @return list<T>
     *
     * @throws InvalidArgumentException
     */
    private static function readGeometries(string $wkb, int &$offset, string $format, int $srid, int $depth, int $maxDepth, string $class): array
    {
        $geometries = [];

        for ($count = self::readInteger($wkb, $offset, $format); $count > 0; $count--) {
            $geometry = self::readGeometry($wkb, $offset, $srid, $depth + 1, $maxDepth);

            if (! $geometry instanceof $class) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid spatial value: expected %s in the WKB, got %s.',
                    class_basename($class),
                    class_basename($geometry),
                ));
            }

            $geometries[] = $geometry;
        }

        return $geometries;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function readInteger(string $wkb, int &$offset, string $format): int
    {
        /** @var array{1: int} $integer */
        $integer = unpack($format, self::take($wkb, $offset, 4));

        return $integer[1];
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function take(string $wkb, int &$offset, int $length): string
    {
        self::checkLength($wkb, $offset, $length);
        $bytes = substr($wkb, $offset, $length);
        $offset += $length;

        return $bytes;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function checkLength(string $wkb, int $offset, int $length): void
    {
        if ($length > strlen($wkb) - $offset) {
            throw new InvalidArgumentException('Invalid spatial value: unexpected end of the WKB.');
        }
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
