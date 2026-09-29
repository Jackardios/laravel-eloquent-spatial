<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Closure;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

/**
 * Reads WKT and EWKT with the grammar of brick/geo. Z and M coordinates are dropped.
 *
 * Brick/geo splits the whole value into tokens first, which takes about 400 bytes of memory for every number, comma
 * and parenthesis.
 *
 * @internal
 */
final class Wkt
{
    private const string NUMBER = '(-?\d++(?:\.\d++)?+(?:[eE][+-]?\d++)?+)';

    /**
     * The coordinates of a point by the number of its dimensions. Numbers need no whitespace between them, as in
     * brick/geo: POINT(1-2) is POINT(1 -2).
     */
    private const array POINT_PATTERNS = [
        2 => '/\G\s*+'.self::NUMBER.'\s*+'.self::NUMBER.'/',
        3 => '/\G\s*+'.self::NUMBER.'\s*+'.self::NUMBER.'\s*+'.self::NUMBER.'/',
        4 => '/\G\s*+'.self::NUMBER.'\s*+'.self::NUMBER.'\s*+'.self::NUMBER.'\s*+'.self::NUMBER.'/',
    ];

    private const string WHITESPACE = " \t\n\v\f\r";

    private const string LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private int $offset = 0;

    private function __construct(
        private readonly string $wkt,
        private readonly int $maxDepth,
    ) {}

    /**
     * @param  int|null  $srid  The SRID of the geometry, or null for the SRID of the EWKT.
     * @param  int  $defaultSrid  The SRID if it is null and the value is not EWKT.
     *
     * @throws InvalidArgumentException
     */
    public static function read(string $wkt, ?int $srid, int $defaultSrid, int $maxDepth): Geometry
    {
        $reader = new self($wkt, $maxDepth);
        $ewktSrid = $reader->readSrid();
        $geometry = $reader->readGeometry($srid ?? $ewktSrid ?? $defaultSrid, 1, null);
        $reader->skipWhitespace();

        if ($reader->offset !== strlen($wkt)) {
            throw $reader->expected('the end');
        }

        return $geometry;
    }

    private function readSrid(): ?int
    {
        if (preg_match('/^\s*+SRID=(\d++)\s*+;/i', $this->wkt, $matches) !== 1) {
            return null;
        }

        $this->offset = strlen($matches[0]);

        return (int) $matches[1];
    }

    /**
     * @param  string|null  $collectionDimensions  The Z and M of the collection that the geometry is in, which the
     *                                             geometry must have too.
     *
     * @throws InvalidArgumentException
     */
    private function readGeometry(int $srid, int $depth, ?string $collectionDimensions): Geometry
    {
        $type = $this->readWord() ?? throw $this->expected('a geometry type');
        $word = $this->readWord();
        $dimensions = in_array($word, ['Z', 'M', 'ZM'], true) ? $word : '';

        if ($dimensions !== '') {
            $word = $this->readWord();
        }

        if ($word !== null && $word !== 'EMPTY') {
            throw new InvalidArgumentException("Invalid spatial value: unexpected word {$word} in the WKT.");
        }

        if ($collectionDimensions !== null && $dimensions !== $collectionDimensions) {
            throw new InvalidArgumentException(
                'Invalid spatial value: the geometries in a WKT geometry collection must have its Z and M coordinates, if any.'
            );
        }

        $isEmpty = $word === 'EMPTY';
        $coordinates = 2 + strlen($dimensions);

        if (! $isEmpty && $depth >= $this->maxDepth
            && in_array($type, ['MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRYCOLLECTION'], true)) {
            throw Wkb::tooDeep($this->maxDepth);
        }

        return match ($type) {
            'POINT' => $isEmpty
                ? throw new InvalidArgumentException('Invalid spatial value: empty points are not supported.')
                : $this->readPointText($coordinates, $srid),
            'LINESTRING' => new EloquentSpatial::$lineString($isEmpty ? [] : $this->readPoints($coordinates, $srid), $srid),
            'POLYGON' => new EloquentSpatial::$polygon($isEmpty ? [] : $this->readLineStrings($coordinates, $srid), $srid),
            'MULTIPOINT' => new EloquentSpatial::$multiPoint($isEmpty ? [] : $this->readPoints($coordinates, $srid), $srid),
            'MULTILINESTRING' => new EloquentSpatial::$multiLineString($isEmpty ? [] : $this->readLineStrings($coordinates, $srid), $srid),
            'MULTIPOLYGON' => new EloquentSpatial::$multiPolygon($isEmpty ? [] : $this->readList(
                fn (): Polygon => new EloquentSpatial::$polygon($this->readLineStrings($coordinates, $srid), $srid),
            ), $srid),
            'GEOMETRYCOLLECTION' => new EloquentSpatial::$geometryCollection($isEmpty ? [] : $this->readList(
                fn (): Geometry => $this->readGeometry($srid, $depth + 1, $dimensions),
            ), $srid),
            default => throw new InvalidArgumentException("Invalid spatial value: unsupported WKT geometry type {$type}."),
        };
    }

    /**
     * @throws InvalidArgumentException
     */
    private function readPointText(int $coordinates, int $srid): Point
    {
        $this->expect('(');
        $point = $this->readPoint($coordinates, $srid);
        $this->expect(')');

        return $point;
    }

    /**
     * Each point can be in parentheses, as in MULTIPOINT((1 2), (3 4)), and so can the points of a line.
     *
     * @return list<Point>
     *
     * @throws InvalidArgumentException
     */
    private function readPoints(int $coordinates, int $srid): array
    {
        return $this->readList(function () use ($coordinates, $srid): Point {
            if (! $this->accept('(')) {
                return $this->readPoint($coordinates, $srid);
            }

            $point = $this->readPoint($coordinates, $srid);
            $this->expect(')');

            return $point;
        });
    }

    /**
     * @return list<LineString>
     *
     * @throws InvalidArgumentException
     */
    private function readLineStrings(int $coordinates, int $srid): array
    {
        return $this->readList(
            fn (): LineString => new EloquentSpatial::$lineString($this->readPoints($coordinates, $srid), $srid),
        );
    }

    /**
     * Reads a list in parentheses, separated by commas.
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return list<T>
     *
     * @throws InvalidArgumentException
     */
    private function readList(Closure $read): array
    {
        $this->expect('(');
        $items = [];

        do {
            $items[] = $read();
        } while ($this->readSeparator());

        return $items;
    }

    /**
     * @return bool True for a comma, false for the closing parenthesis.
     *
     * @throws InvalidArgumentException
     */
    private function readSeparator(): bool
    {
        return match (true) {
            $this->accept(',') => true,
            $this->accept(')') => false,
            default => throw $this->expected("',' or ')'"),
        };
    }

    /**
     * @throws InvalidArgumentException
     */
    private function readPoint(int $coordinates, int $srid): Point
    {
        if (preg_match(self::POINT_PATTERNS[$coordinates], $this->wkt, $matches, 0, $this->offset) !== 1) {
            throw $this->expected("{$coordinates} coordinates");
        }

        $this->offset += strlen($matches[0]);

        return new EloquentSpatial::$point((float) $matches[1], (float) $matches[2], $srid);
    }

    /**
     * @phpstan-impure
     */
    private function readWord(): ?string
    {
        $this->skipWhitespace();
        $length = strspn($this->wkt, self::LETTERS, $this->offset);

        if ($length === 0) {
            return null;
        }

        $word = strtoupper(substr($this->wkt, $this->offset, $length));
        $this->offset += $length;

        return $word;
    }

    /**
     * @phpstan-impure
     */
    private function accept(string $character): bool
    {
        $this->skipWhitespace();

        if (($this->wkt[$this->offset] ?? '') !== $character) {
            return false;
        }

        $this->offset++;

        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function expect(string $character): void
    {
        if (! $this->accept($character)) {
            throw $this->expected("'{$character}'");
        }
    }

    private function skipWhitespace(): void
    {
        $this->offset += strspn($this->wkt, self::WHITESPACE, $this->offset);
    }

    private function expected(string $what): InvalidArgumentException
    {
        return new InvalidArgumentException(
            sprintf('Invalid spatial value: expected %s at offset %d of the WKT.', $what, $this->offset)
        );
    }
}
