<?php

declare(strict_types=1);

namespace Jackardios\EloquentSpatial;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\MultiPolygon;
use Jackardios\EloquentSpatial\Objects\Polygon;
use JsonException;

class BoundingBoxCast implements CastsAttributes
{
    public const FORMAT_GEOMETRY = 'geometry';

    public const FORMAT_JSON = 'json';

    private string $format;

    public function __construct(string $format = self::FORMAT_GEOMETRY)
    {
        if (! in_array($format, [self::FORMAT_GEOMETRY, self::FORMAT_JSON], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid format "%s". Supported formats: %s, %s', $format, self::FORMAT_GEOMETRY, self::FORMAT_JSON)
            );
        }

        $this->format = $format;
    }

    /**
     * @param  Model  $model
     * @param  string|ExpressionContract|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function get($model, string $key, $value, array $attributes): ?BoundingBox
    {
        if (! $value) {
            return null;
        }

        if ($this->format === self::FORMAT_JSON) {
            if (! is_string($value)) {
                throw new InvalidArgumentException('JSON format expects string value from database');
            }

            return $this->fromJson($value);
        }

        return $this->fromGeometry($model, $value);
    }

    /**
     * @param  string|ExpressionContract  $value
     */
    private function fromGeometry(Model $model, $value): BoundingBox
    {
        if ($value instanceof ExpressionContract) {
            $grammar = $model->getConnection()->getQueryGrammar();
            $expressionValue = (string) $value->getValue($grammar);
            ['wkt' => $wkt, 'srid' => $srid] = Helper::parseStGeomFromText($expressionValue);

            return $this->geometryToBoundingBox(Geometry::fromWkt($wkt, $srid));
        }

        return $this->geometryToBoundingBox(Geometry::fromWkb($value));
    }

    private function fromJson(string $value): BoundingBox
    {
        try {
            /** @var array $array */
            $array = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return BoundingBox::fromArray($array);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON for BoundingBox: '.$e->getMessage(), 0, $e);
        }
    }

    private function geometryToBoundingBox(Geometry $geometry): BoundingBox
    {
        if ($geometry instanceof Polygon || $geometry instanceof MultiPolygon) {
            return $geometry->toBoundingBox();
        }

        throw new InvalidArgumentException(
            sprintf('Expected Polygon or MultiPolygon, %s given.', $geometry::class)
        );
    }

    /**
     * @param  Model  $model
     * @param  BoundingBox|array<string, float>|mixed|null  $value
     * @param  array<string, mixed>  $attributes
     * @return ExpressionContract|string|null
     *
     * @throws InvalidArgumentException
     */
    public function set($model, string $key, $value, array $attributes): mixed
    {
        if (! $value) {
            return null;
        }

        if (is_array($value)) {
            /** @var array $value */
            $value = BoundingBox::fromArray($value);
        }

        if ($value instanceof ExpressionContract) {
            return $value;
        }

        if (! ($value instanceof BoundingBox)) {
            $bboxType = is_object($value) ? $value::class : gettype($value);
            throw new InvalidArgumentException(
                sprintf('Expected %s, %s given.', BoundingBox::class, $bboxType)
            );
        }

        if ($this->format === self::FORMAT_JSON) {
            return $value->toJson();
        }

        return $value->toGeometry()->toSqlExpression($model->getConnection());
    }
}
