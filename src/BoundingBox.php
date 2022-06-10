<?php

declare(strict_types=1);

namespace MatanYadaev\EloquentSpatial;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidBoundingBoxPoints;
use MatanYadaev\EloquentSpatial\Objects\GeometryCollection;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

class BoundingBox implements Arrayable, Jsonable, JsonSerializable, Castable
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

    public function toJson($options = 0): string
    {
        return json_encode($this, $options);
    }

    #[ArrayShape(['left' => 'float', 'bottom' => 'float', 'right' => 'float', 'top' => 'float'])]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            /**
             * @param Model $model
             * @param string $key
             * @param string|null $wkb
             * @param array<string, mixed> $attributes
             *
             * @return BoundingBox
             */
            public function get($model, string $key, $wkb, $attributes)
            {
                if (! $wkb) {
                    return null;
                }

                return Polygon::fromWkb($wkb)->toBoundingBox();
            }

            /**
             * @param Model $model
             * @param string $key
             * @param BoundingBox|mixed|null $bbox
             * @param array<string, mixed> $attributes
             *
             * @return Expression|string|null
             *
             * @throws InvalidArgumentException
             */
            public function set($model, string $key, $bbox, $attributes)
            {
                if (! $bbox) {
                    return null;
                }

                if (! ($bbox instanceof BoundingBox)) {
                    $bboxType = is_object($bbox) ? $bbox::class : gettype($bbox);
                    throw new InvalidArgumentException(
                        sprintf('Expected %s, %s given.', static::class, $bboxType)
                    );
                }

                return $bbox->toPolygon()->toWkt();
            }
        };
    }
}
