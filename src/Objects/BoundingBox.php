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
use Jackardios\EloquentSpatial\Exceptions\InvalidBoundingBoxPoints;
use Jackardios\EloquentSpatial\Exceptions\InvalidGeometry;
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
        $this->leftBottom = $leftBottom;
        $this->rightTop = $rightTop;
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    protected function validatePoints(Point $leftBottom, Point $rightTop): void
    {
        if ($rightTop->latitude <= $leftBottom->latitude) {
            throw new InvalidBoundingBoxPoints('The latitude of the bottom point must be less than the latitude of the top point');
        }
    }

    public function getLeftBottom(): Point
    {
        return $this->leftBottom;
    }

    public function getRightTop(): Point
    {
        return $this->rightTop;
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
        if ($minPadding < 0) {
            throw new InvalidArgumentException('minPadding must be non-negative');
        }

        $longitudes = [];
        $bottom = $top = null;

        foreach ($points as $point) {
            [$longitude, $latitude] = $point->getCoordinates();
            $longitudes[] = $longitude;

            if (! isset($bottom) || $latitude < $bottom) {
                $bottom = $latitude;
            }
            if (! isset($top) || $latitude > $top) {
                $top = $latitude;
            }
        }

        if (empty($longitudes) || ! isset($bottom) || ! isset($top)) {
            throw new InvalidArgumentException('cannot create bounding box from empty points');
        }

        [$left, $right] = self::findShortestLongitudeArc($longitudes);

        $crossesAntimeridian = $left > $right;
        $lonSpan = $crossesAntimeridian
            ? (180.0 - $left) + ($right + 180.0)
            : $right - $left;

        if ($lonSpan < $minPadding) {
            $halfPadding = ($minPadding - $lonSpan) / 2;
            $left = self::normalizeLongitude($left - $halfPadding);
            $right = self::normalizeLongitude($right + $halfPadding);
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
        while ($longitude > 180.0) {
            $longitude -= 360.0;
        }
        while ($longitude < -180.0) {
            $longitude += 360.0;
        }

        return $longitude;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function toPolygon(): Polygon
    {
        if ($this->crossesAntimeridian()) {
            throw new InvalidArgumentException(
                'Cannot convert antimeridian-crossing bounding box to single Polygon. Use toGeometry() instead.'
            );
        }

        return $this->createPolygon($this->leftBottom->longitude, $this->rightTop->longitude);
    }

    public function toGeometry(): Polygon|MultiPolygon
    {
        if (! $this->crossesAntimeridian()) {
            return $this->createPolygon($this->leftBottom->longitude, $this->rightTop->longitude);
        }

        return new MultiPolygon([
            $this->createPolygon($this->leftBottom->longitude, 180.0),
            $this->createPolygon(-180.0, $this->rightTop->longitude),
        ]);
    }

    protected function createPolygon(float $left, float $right): Polygon
    {
        $bottom = $this->leftBottom->latitude;
        $top = $this->rightTop->latitude;

        // Counter-clockwise winding order (GeoJSON RFC 7946)
        return new Polygon([
            new LineString([
                new Point($left, $bottom),
                new Point($right, $bottom),
                new Point($right, $top),
                new Point($left, $top),
                new Point($left, $bottom),
            ]),
        ]);
    }

    /**
     * @param  array  $array
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
     * @param  array<string>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        $format = $arguments[0] ?? BoundingBoxCast::FORMAT_GEOMETRY;

        return new BoundingBoxCast($format);
    }
}
