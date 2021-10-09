<?php

declare(strict_types=1);

namespace MatanYadaev\EloquentSpatial;

use Illuminate\Support\Collection;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidBoundingBoxPoints;
use MatanYadaev\EloquentSpatial\Objects\GeometryCollection;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

class BoundingBox
{
    protected Point $leftBottom;
    protected Point $rightTop;

    public function __construct(Point $leftBottom, Point $rightTop)
    {
        $this->validatePoints($leftBottom, $rightTop);
        $this->leftBottom = $leftBottom;
        $this->rightTop = $rightTop;
    }

    protected function validatePoints(Point $leftBottom, Point $rightTop): void
    {
        if ($rightTop->getLongitude() <= $leftBottom->getLongitude()) {
            throw new InvalidBoundingBoxPoints('The longitude of $leftBottom Point must be less than the longitude of $rightBottom Point');
        }

        if ($rightTop->getLatitude() <= $leftBottom->getLatitude()) {
            throw new InvalidBoundingBoxPoints('The latitude of $leftBottom Point must be less than the latitude of $rightBottom Point');
        }
    }

    /**
     * @param GeometryCollection $geometryCollection
     *
     * @return BoundingBox
     */
    public static function fromGeometryCollection(GeometryCollection $geometryCollection): self
    {
        return self::fromPoints($geometryCollection->getPoints());
    }

    /**
     * @param Point[]|Collection<Point> $points
     *
     * @return BoundingBox
     */
    public static function fromPoints(array|Collection $points): self
    {
        $left = $bottom = $right = $top = null;
        foreach ($points as $point) {
            [$longitude, $latitude] = $point->getCoordinates();

            if (!isset($left) || $longitude < $left) {
                $left = $longitude;
            }
            if (!isset($right) || $longitude > $right) {
                $right = $longitude;
            }
            if (!isset($bottom) || $latitude < $bottom) {
                $bottom = $latitude;
            }
            if (!isset($top) || $latitude > $top) {
                $top = $latitude;
            }
        }

        return new self(new Point($left, $bottom), new Point($right, $top));
    }

    public function toPolygon(): Polygon
    {
        ['left' => $left, 'bottom' => $bottom, 'right' => $right, 'top' => $top] = $this->toArray();
        return new Polygon([
            new LineString([
                new Point($left, $top),
                new Point($right, $top),
                new Point($right, $bottom),
                new Point($left, $bottom),
                new Point($left, $top),
            ])
        ]);
    }

    #[Pure]
    #[ArrayShape(['left' => 'float', 'bottom' => 'float', 'right' => 'float', 'top' => 'float'])]
    public function toArray(): array
    {
        return [
            'left' => $this->leftBottom->getLongitude(),
            'bottom' => $this->leftBottom->getLatitude(),
            'right' => $this->rightTop->getLongitude(),
            'top' => $this->rightTop->getLatitude(),
        ];
    }
}
