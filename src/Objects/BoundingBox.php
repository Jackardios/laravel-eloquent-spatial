<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial\Objects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\BoundingBoxCast;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Exceptions\InvalidBoundingBoxPoints;
use Jackardios\EloquentSpatial\Exceptions\InvalidGeometry;
use Jackardios\EloquentSpatial\Helper;
use JsonException;
use JsonSerializable;
use Stringable;

class BoundingBox implements Arrayable, Castable, Jsonable, JsonSerializable, Stringable
{
    protected Point $leftBottom;

    protected Point $rightTop;

    public function __construct(Point $leftBottom, Point $rightTop)
    {
        $this->validatePoints($leftBottom, $rightTop);
        // Points are mutable, so copies are kept and returned, and a change to a point cannot bypass the validation.
        $this->leftBottom = clone $leftBottom;
        $this->rightTop = clone $rightTop;
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    protected function validatePoints(Point $leftBottom, Point $rightTop): void
    {
        self::validateRanges($leftBottom);
        self::validateRanges($rightTop);

        // A box of a single point or a horizontal line has the same top and bottom.
        if ($rightTop->latitude < $leftBottom->latitude) {
            throw new InvalidBoundingBoxPoints('The latitude of the bottom point must not be greater than the latitude of the top point');
        }
    }

    /**
     * The point can have a projected SRID, which Point does not validate, but a bounding box is always in degrees.
     */
    protected static function validateRanges(Point $point): void
    {
        if ($point->longitude < -180 || $point->longitude > 180) {
            throw new InvalidBoundingBoxPoints("Bounding box longitudes must be between -180 and 180, got: {$point->longitude}");
        }
        if ($point->latitude < -90 || $point->latitude > 90) {
            throw new InvalidBoundingBoxPoints("Bounding box latitudes must be between -90 and 90, got: {$point->latitude}");
        }
    }

    public function getLeftBottom(): Point
    {
        return clone $this->leftBottom;
    }

    public function getRightTop(): Point
    {
        return clone $this->rightTop;
    }

    public function crossesAntimeridian(): bool
    {
        return $this->leftBottom->longitude > $this->rightTop->longitude;
    }

    public static function fromGeometry(Geometry $geometry, float $minPadding = 0): self
    {
        if ($geometry instanceof GeometryCollection) {
            return self::fromPoints($geometry->getPoints(), $minPadding);
        }

        if ($geometry instanceof Point) {
            return self::fromPoints([$geometry], $minPadding);
        }

        $geometryClass = $geometry::class;

        throw new InvalidGeometry("cannot create bounding box from $geometryClass");
    }

    /**
     * @param  array<int, Point>|Collection<int, Point>  $points
     */
    public static function fromPoints(array|Collection $points, float $minPadding = 0): self
    {
        if (! ($minPadding >= 0) || is_infinite($minPadding)) {
            throw new InvalidArgumentException('minPadding must be non-negative and finite');
        }

        $longitudes = [];
        $latitudes = [];

        foreach ($points as $point) {
            self::validateRanges($point);
            [$longitudes[], $latitudes[]] = $point->getCoordinates();
        }

        if ($latitudes === []) {
            throw new InvalidArgumentException('cannot create bounding box from empty points');
        }

        $bottom = min($latitudes);
        $top = max($latitudes);

        [$left, $right] = self::findShortestLongitudeArc($longitudes);

        $crossesAntimeridian = $left > $right;
        $lonSpan = $crossesAntimeridian
            ? (180.0 - $left) + ($right + 180.0)
            : $right - $left;

        if ($lonSpan < $minPadding) {
            if ($minPadding >= 360.0) {
                $left = -180.0;
                $right = 180.0;
            } else {
                $halfPadding = ($minPadding - $lonSpan) / 2;
                $left = self::normalizeLongitude($left - $halfPadding);
                $right = self::normalizeLongitude($right + $halfPadding);
            }
        }

        $latPadding = $top - $bottom;
        if ($latPadding < $minPadding) {
            $halfPadding = ($minPadding - $latPadding) / 2;
            $bottom = max(-90.0, $bottom - $halfPadding);
            $top = min(90.0, $top + $halfPadding);
        }

        return new self(new Point($left, $bottom), new Point($right, $top));
    }

    /**
     * @param  array<int, float>  $longitudes
     * @return array{0: float, 1: float}
     */
    protected static function findShortestLongitudeArc(array $longitudes): array
    {
        $longitudes = array_unique($longitudes);
        sort($longitudes);

        $count = count($longitudes);
        if ($count === 1) {
            return [$longitudes[0], $longitudes[0]];
        }

        $maxGap = 0;
        $maxGapIndex = 0;

        for ($i = 0; $i < $count; $i++) {
            $next = ($i + 1) % $count;
            $gap = $next === 0
                ? ($longitudes[0] + 360.0) - $longitudes[$count - 1]
                : $longitudes[$next] - $longitudes[$i];

            if ($gap > $maxGap) {
                $maxGap = $gap;
                $maxGapIndex = $i;
            }
        }

        $rightIndex = $maxGapIndex;
        $leftIndex = ($maxGapIndex + 1) % $count;

        return [$longitudes[$leftIndex], $longitudes[$rightIndex]];
    }

    protected static function normalizeLongitude(float $longitude): float
    {
        if ($longitude >= -180.0 && $longitude <= 180.0) {
            return $longitude;
        }

        $longitude = fmod($longitude + 180.0, 360.0);

        return ($longitude < 0 ? $longitude + 360.0 : $longitude) - 180.0;
    }

    /**
     * @param  int|Srid|null  $srid  The SRID of the polygon, or null for the default SRID.
     *
     * @throws InvalidArgumentException
     */
    public function toPolygon(int|Srid|null $srid = null): Polygon
    {
        if ($this->crossesAntimeridian()) {
            throw new InvalidArgumentException(
                'Cannot convert antimeridian-crossing bounding box to single Polygon. Use toGeometry() instead.'
            );
        }

        return $this->createPolygon($this->leftBottom->longitude, $this->rightTop->longitude, Helper::getSrid($srid));
    }

    /**
     * @param  int|Srid|null  $srid  The SRID of the geometry, or null for the default SRID.
     */
    public function toGeometry(int|Srid|null $srid = null): Polygon|MultiPolygon
    {
        $srid = Helper::getSrid($srid);

        if (! $this->crossesAntimeridian()) {
            return $this->createPolygon($this->leftBottom->longitude, $this->rightTop->longitude, $srid);
        }

        return new MultiPolygon([
            $this->createPolygon($this->leftBottom->longitude, 180.0, $srid),
            $this->createPolygon(-180.0, $this->rightTop->longitude, $srid),
        ], $srid);
    }

    protected function createPolygon(float $left, float $right, int $srid): Polygon
    {
        $bottom = $this->leftBottom->latitude;
        $top = $this->rightTop->latitude;

        // Counter-clockwise winding order (GeoJSON RFC 7946)
        return new Polygon([
            new LineString([
                new Point($left, $bottom, $srid),
                new Point($right, $bottom, $srid),
                new Point($right, $top, $srid),
                new Point($left, $top, $srid),
                new Point($left, $bottom, $srid),
            ], $srid),
        ], $srid);
    }

    /**
     * @param  array<string, mixed>  $array
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $array): self
    {
        if (! isset($array['left'], $array['bottom'], $array['right'], $array['top'])) {
            throw new InvalidArgumentException(
                'Array must contain keys: left, bottom, right, top'
            );
        }

        $left = $array['left'];
        $bottom = $array['bottom'];
        $right = $array['right'];
        $top = $array['top'];

        if (! is_numeric($left) || ! is_numeric($bottom) || ! is_numeric($right) || ! is_numeric($top)) {
            throw new InvalidArgumentException(
                'Array values for left, bottom, right, top must be numeric'
            );
        }

        return new self(
            new Point((float) $left, (float) $bottom),
            new Point((float) $right, (float) $top)
        );
    }

    /**
     * @return array{left: float,bottom: float,right: float,top: float}
     */
    public function toArray(): array
    {
        return [
            'left' => $this->leftBottom->longitude,
            'bottom' => $this->leftBottom->latitude,
            'right' => $this->rightTop->longitude,
            'top' => $this->rightTop->latitude,
        ];
    }

    /**
     * @param  int  $options
     *
     * @throws JsonException
     */
    public function toJson($options = 0): string
    {
        return json_encode($this, $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{left: float,bottom: float,right: float,top: float}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * The arguments are the format, "geometry" (the default) or "json", and for the geometry format an optional SRID,
     * for example BoundingBox::class.':geometry,4326'.
     *
     * @param  array<string>  $arguments
     *
     * @throws InvalidArgumentException
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        $format = $arguments[0] ?? BoundingBoxCast::FORMAT_GEOMETRY;
        $srid = $arguments[1] ?? null;

        if ($srid !== null && ! ctype_digit($srid)) {
            throw new InvalidArgumentException(sprintf('Invalid SRID "%s".', $srid));
        }

        return new BoundingBoxCast($format, $srid === null ? null : (int) $srid);
    }
}
