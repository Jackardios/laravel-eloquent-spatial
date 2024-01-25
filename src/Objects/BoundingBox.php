<?php

declare(strict_types=1);

namespace MatanYadaev\EloquentSpatial\Objects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidBoundingBoxPoints;
use MatanYadaev\EloquentSpatial\Exceptions\InvalidGeometry;
use Stringable;

class BoundingBox implements Castable, Arrayable, Jsonable, JsonSerializable, Stringable
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
    if ($rightTop->getLongitude() <= $leftBottom->getLongitude()) {
      throw new InvalidBoundingBoxPoints('The longitude of $leftBottom Point must be less than the longitude of $rightBottom Point');
    }

    if ($rightTop->getLatitude() <= $leftBottom->getLatitude()) {
      throw new InvalidBoundingBoxPoints('The latitude of $leftBottom Point must be less than the latitude of $rightBottom Point');
    }
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
   * @return BoundingBox
   */
  public static function fromPoints(array|Collection $points, float $minPadding = 0): self
  {
    $left = $bottom = $right = $top = null;
    foreach ($points as $point) {
      [$longitude, $latitude] = $point->getCoordinates();

      if (! isset($left) || $longitude < $left) {
        $left = $longitude;
      }
      if (! isset($right) || $longitude > $right) {
        $right = $longitude;
      }
      if (! isset($bottom) || $latitude < $bottom) {
        $bottom = $latitude;
      }
      if (! isset($top) || $latitude > $top) {
        $top = $latitude;
      }
    }

    if (! isset($left) || ! isset($right) || ! isset($bottom) || ! isset($top)) {
      throw new InvalidArgumentException('cannot create bounding box from empty points');
    }

    $lonPadding = $right - $left;
    if ($lonPadding < $minPadding) {
      $halfPadding = ($minPadding - $lonPadding) / 2;
      $left -= $halfPadding;
      $right += $halfPadding;
    }

    $latPadding = $top - $bottom;
    if ($latPadding < $minPadding) {
      $halfPadding = ($minPadding - $latPadding) / 2;
      $bottom -= $halfPadding;
      $top += $halfPadding;
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
      ]),
    ]);
  }

  /**
   * @return array{left: float,bottom: float,right: float,top: float}
   */
  public function toArray(): array
  {
    return [
      'left' => $this->leftBottom->getLongitude(),
      'bottom' => $this->leftBottom->getLatitude(),
      'right' => $this->rightTop->getLongitude(),
      'top' => $this->rightTop->getLatitude(),
    ];
  }

  /**
   * @param  int  $options
   * @return string
   *
   * @throws JsonException
   */
  public function toJson($options = 0): string
  {
    return json_encode($this, $options | JSON_THROW_ON_ERROR);
  }

  /**
   * @return array<mixed>
   */
  public function jsonSerialize(): array
  {
    return $this->toArray();
  }

  /**
   * @param  array<string>  $arguments
   * @return CastsAttributes
   */
  public static function castUsing(array $arguments): CastsAttributes
  {
    return new class implements CastsAttributes {
      /**
       * @param  Model  $model
       * @param  string  $key
       * @param  string|null  $wkb
       * @param  array<string, mixed>  $attributes
       * @return BoundingBox|null
       */
      public function get($model, string $key, $wkb, $attributes): ?BoundingBox
      {
        if (! $wkb) {
          return null;
        }

        return Polygon::fromWkb($wkb)->toBoundingBox();
      }

      /**
       * @param  Model  $model
       * @param  string  $key
       * @param  BoundingBox|mixed|null  $bbox
       * @param  array<string, mixed>  $attributes
       * @return ExpressionContract|null
       *
       * @throws InvalidArgumentException
       */
      public function set($model, string $key, $bbox, $attributes): ?ExpressionContract
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

        return $bbox->toPolygon()->toSqlExpression($model->getConnection());
      }
    };
  }
}
