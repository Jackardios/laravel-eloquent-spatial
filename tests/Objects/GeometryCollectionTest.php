<?php

use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Objects\Geometry;
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\MultiPoint;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Tests\TestModels\TestExtendedPlace;
use Jackardios\EloquentSpatial\Tests\TestModels\TestPlace;
use Jackardios\EloquentSpatial\Tests\TestObjects\ExtendedGeometryCollection;

it('creates a model record with geometry collection', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry_collection' => $geometryCollection])->fresh();

    expect($testPlace->geometry_collection)->toBeInstanceOf(GeometryCollection::class);
    expect($testPlace->geometry_collection)->toEqual($geometryCollection);
});

it('creates a model record with geometry collection with SRID integer', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ], Srid::WGS84->value);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry_collection' => $geometryCollection])->fresh();

    expect($testPlace->geometry_collection->srid)->toBe(Srid::WGS84->value);
});

it('creates a model record with geometry collection with SRID enum', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ], Srid::WGS84);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry_collection' => $geometryCollection])->fresh();

    expect($testPlace->geometry_collection->srid)->toBe(Srid::WGS84->value);
});

it('creates geometry collection with default 0 SRID from JSON', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(0);
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    // Act
    $geometryCollectionFromJson = GeometryCollection::fromJson('{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]},{"type":"Point","coordinates":[180,0]}]}');

    // Assert
    expect($geometryCollectionFromJson)->toEqual($geometryCollection);
    expect($geometryCollectionFromJson->srid)->toBe(0);
});

it('creates geometry collection with default 4326 SRID from JSON', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(Srid::WGS84);
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    // Act
    $geometryCollectionFromJson = GeometryCollection::fromJson('{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]},{"type":"Point","coordinates":[180,0]}]}');

    // Assert
    expect($geometryCollectionFromJson->toWkt())->toBe($geometryCollection->toWkt());
    expect($geometryCollectionFromJson->srid)->toBe(Srid::WGS84->value);

    // Cleanup
    EloquentSpatial::setDefaultSrid(0);
});

it('creates geometry collection with SRID from JSON', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0, Srid::WGS84->value),
                new Point(179, 1, Srid::WGS84->value),
                new Point(178, 2, Srid::WGS84->value),
                new Point(177, 3, Srid::WGS84->value),
                new Point(180, 0, Srid::WGS84->value),
            ], Srid::WGS84->value),
        ], Srid::WGS84->value),
        new Point(180, 0, Srid::WGS84->value),
    ], Srid::WGS84->value);

    $geometryCollectionFromJson = GeometryCollection::fromJson('{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]},{"type":"Point","coordinates":[180,0]}]}', Srid::WGS84->value);

    expect($geometryCollectionFromJson)->toEqual($geometryCollection);
});

it('creates geometry collection with default 0 SRID from array', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(0);
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    // Act
    $geometryCollectionFromJson = GeometryCollection::fromArray(json_decode('{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]},{"type":"Point","coordinates":[180,0]}]}', true));

    // Assert
    expect($geometryCollectionFromJson)->toEqual($geometryCollection);
    expect($geometryCollectionFromJson->srid)->toBe(0);
});

it('creates geometry collection with default 4326 SRID from array', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(Srid::WGS84);
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    // Act
    $geometryCollectionFromJson = GeometryCollection::fromArray(json_decode('{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]},{"type":"Point","coordinates":[180,0]}]}', true));

    // Assert
    expect($geometryCollectionFromJson->toWkt())->toBe($geometryCollection->toWkt());
    expect($geometryCollectionFromJson->srid)->toBe(Srid::WGS84->value);

    // Cleanup
    EloquentSpatial::setDefaultSrid(0);
});

it('creates geometry collection with SRID from array', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0, Srid::WGS84->value),
                new Point(179, 1, Srid::WGS84->value),
                new Point(178, 2, Srid::WGS84->value),
                new Point(177, 3, Srid::WGS84->value),
                new Point(180, 0, Srid::WGS84->value),
            ], Srid::WGS84->value),
        ], Srid::WGS84->value),
        new Point(180, 0, Srid::WGS84->value),
    ], Srid::WGS84->value);

    $geometryCollectionFromJson = GeometryCollection::fromArray(json_decode('{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]},{"type":"Point","coordinates":[180,0]}]}', true), Srid::WGS84->value);

    expect($geometryCollectionFromJson)->toEqual($geometryCollection);
});

it('creates geometry collection from feature collection JSON', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    $geometryCollectionFromFeatureCollectionJson = GeometryCollection::fromJson('{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}},{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[180,0]}}]}');

    expect($geometryCollectionFromFeatureCollectionJson)->toEqual($geometryCollection);
});

it('creates geometry collection from feature collection with SRID from JSON', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0, Srid::WGS84),
                new Point(179, 1, Srid::WGS84),
                new Point(178, 2, Srid::WGS84),
                new Point(177, 3, Srid::WGS84),
                new Point(180, 0, Srid::WGS84),
            ], Srid::WGS84),
        ], Srid::WGS84),
        new Point(180, 0, Srid::WGS84),
    ], Srid::WGS84);

    $geometryCollectionFromFeatureCollectionJson = GeometryCollection::fromJson('{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}},{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[180,0]}}]}', Srid::WGS84);

    expect($geometryCollectionFromFeatureCollectionJson)->toEqual($geometryCollection);
});

it('creates geometry collection from feature collection from array', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    $geometryCollectionFromFeatureCollectionJson = GeometryCollection::fromArray(json_decode('{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}},{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[180,0]}}]}', true));

    expect($geometryCollectionFromFeatureCollectionJson)->toEqual($geometryCollection);
});

it('creates geometry collection from feature collection with SRID from array', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0, Srid::WGS84),
                new Point(179, 1, Srid::WGS84),
                new Point(178, 2, Srid::WGS84),
                new Point(177, 3, Srid::WGS84),
                new Point(180, 0, Srid::WGS84),
            ], Srid::WGS84),
        ], Srid::WGS84),
        new Point(180, 0, Srid::WGS84),
    ], Srid::WGS84);

    $geometryCollectionFromFeatureCollectionJson = GeometryCollection::fromArray(json_decode('{"type":"FeatureCollection","features":[{"type":"Feature","properties":[],"geometry":{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}},{"type":"Feature","properties":[],"geometry":{"type":"Point","coordinates":[180,0]}}]}', true), Srid::WGS84);

    expect($geometryCollectionFromFeatureCollectionJson)->toEqual($geometryCollection);
});

it('generates geometry collection JSON', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    $json = $geometryCollection->toJson();

    $expectedJson = '{"type":"GeometryCollection","geometries":[{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]},{"type":"Point","coordinates":[180,0]}]}';
    expect($json)->toBe($expectedJson);
});

it('generates geometry collection feature collection JSON', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    $featureCollectionJson = $geometryCollection->toFeatureCollectionJson();

    $expectedFeatureCollectionJson = '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Polygon","coordinates":[[[180,0],[179,1],[178,2],[177,3],[180,0]]]}},{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[180,0]}}]}';
    expect($featureCollectionJson)->toBe($expectedFeatureCollectionJson);
});

it('creates geometry collection with default 0 SRID from WKT', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(0);
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    // Act
    $geometryCollectionFromWkt = GeometryCollection::fromWkt('GEOMETRYCOLLECTION(POLYGON((180 0, 179 1, 178 2, 177 3, 180 0)), POINT(180 0))');

    // Assert
    expect($geometryCollectionFromWkt)->toEqual($geometryCollection);
});

it('creates geometry collection with default 4326 SRID from WKT', function (): void {
    // Arrange
    EloquentSpatial::setDefaultSrid(Srid::WGS84);
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    // Act
    $geometryCollectionFromWkt = GeometryCollection::fromWkt('GEOMETRYCOLLECTION(POLYGON((180 0, 179 1, 178 2, 177 3, 180 0)), POINT(180 0))');

    // Assert
    expect($geometryCollectionFromWkt->toWkt())->toBe($geometryCollection->toWkt());
    expect($geometryCollectionFromWkt->srid)->toBe(Srid::WGS84->value);

    // Cleanup
    EloquentSpatial::setDefaultSrid(0);
});

it('creates geometry collection with SRID from WKT', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0, Srid::WGS84->value),
                new Point(179, 1, Srid::WGS84->value),
                new Point(178, 2, Srid::WGS84->value),
                new Point(177, 3, Srid::WGS84->value),
                new Point(180, 0, Srid::WGS84->value),
            ], Srid::WGS84->value),
        ], Srid::WGS84->value),
        new Point(180, 0, Srid::WGS84->value),
    ], Srid::WGS84->value);

    $geometryCollectionFromWkt = GeometryCollection::fromWkt('GEOMETRYCOLLECTION(POLYGON((180 0, 179 1, 178 2, 177 3, 180 0)), POINT(180 0))', Srid::WGS84->value);

    expect($geometryCollectionFromWkt)->toEqual($geometryCollection);
});

it('creates empty geometry collection from WKT', function (): void {
    // Arrange
    $geometryCollection = new GeometryCollection([]);

    // Act
    $geometryCollectionFromWkt = GeometryCollection::fromWkt('GEOMETRYCOLLECTION EMPTY');

    // Assert
    expect($geometryCollectionFromWkt)->toEqual($geometryCollection);
});

it('generates geometry collection WKT', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    $wkt = $geometryCollection->toWkt();

    $expectedWkt = 'GEOMETRYCOLLECTION(POLYGON((180 0, 179 1, 178 2, 177 3, 180 0)), POINT(180 0))';
    expect($wkt)->toBe($expectedWkt);
});

it('generates empty geometry collection WKT', function (): void {
    // Arrange
    $geometryCollection = new GeometryCollection([]);

    // Act
    $wkt = $geometryCollection->toWkt();

    // Assert
    expect($wkt)->toBe('GEOMETRYCOLLECTION EMPTY');
});

it('creates geometry collection from WKB', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    $geometryCollectionFromWkb = GeometryCollection::fromWkb($geometryCollection->toWkb());

    expect($geometryCollectionFromWkb)->toEqual($geometryCollection);
});

it('creates geometry collection with SRID from WKB', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0, Srid::WGS84->value),
                new Point(179, 1, Srid::WGS84->value),
                new Point(178, 2, Srid::WGS84->value),
                new Point(177, 3, Srid::WGS84->value),
                new Point(180, 0, Srid::WGS84->value),
            ], Srid::WGS84->value),
        ], Srid::WGS84->value),
        new Point(180, 0, Srid::WGS84->value),
    ], Srid::WGS84->value);

    $geometryCollectionFromWkb = GeometryCollection::fromWkb($geometryCollection->toWkb());

    expect($geometryCollectionFromWkb)->toEqual($geometryCollection);
});

it('does not throw exception when geometry collection has no geometries', function (): void {
    $geometryCollection = new GeometryCollection([]);

    expect($geometryCollection->getGeometries())->toHaveCount(0);
});

it('unsets geometry collection item', function (): void {
    $point = new Point(180, 0);
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        $point,
    ]);

    unset($geometryCollection[0]);

    expect($geometryCollection[0])->toBe($point);
    expect($geometryCollection->getGeometries())->toHaveCount(1);
});

it('throws exception when unsetting geometry collection item below minimum', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    expect(function () use ($polygon): void {
        unset($polygon[0]);
    })->toThrow(InvalidArgumentException::class);
});

it('checks if geometry collection item is exists', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    $firstItemExists = isset($geometryCollection[0]);
    $secondItemExists = isset($geometryCollection[1]);
    $thirdItemExists = isset($geometryCollection[2]);

    expect($firstItemExists)->toBeTrue();
    expect($secondItemExists)->toBeTrue();
    expect($thirdItemExists)->toBeFalse();
});

it('gets item from geometry collection by offset', function (): void {
    $point = new Point(180, 0);
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);
    $geometryCollection = new GeometryCollection([$polygon, $point]);

    expect($geometryCollection[0])->toBe($polygon);
    expect($geometryCollection[1])->toBe($point);
});

it('sets item to geometry collection', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);
    $lineString = new LineString([
        new Point(180, 0),
        new Point(179, 1),
    ]);

    $geometryCollection[2] = $lineString;

    expect($geometryCollection[2])->toBe($lineString);
});

it('throws exception when setting invalid item to geometry collection', function (): void {
    $polygon = new Polygon([
        new LineString([
            new Point(180, 0),
            new Point(179, 1),
            new Point(178, 2),
            new Point(177, 3),
            new Point(180, 0),
        ]),
    ]);

    expect(function () use ($polygon): void {
        // @phpstan-ignore-next-line
        $polygon[1] = new Point(180, 0);
    })->toThrow(InvalidArgumentException::class);
});

it('casts a GeometryCollection to a string', function (): void {
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    expect($geometryCollection->__toString())->toEqual('GEOMETRYCOLLECTION(POLYGON((180 0, 179 1, 178 2, 177 3, 180 0)), POINT(180 0))');
});

it('adds a macro toGeometryCollection', function (): void {
    Geometry::macro('getName', function (): string {
        /** @var Geometry $this */
        return class_basename($this);
    });

    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ]);

    // @phpstan-ignore-next-line
    expect($geometryCollection->getName())->toBe('GeometryCollection');
});

it('uses an extended GeometryCollection class', function (): void {
    // Arrange
    EloquentSpatial::useGeometryCollection(ExtendedGeometryCollection::class);
    $geometryCollection = new ExtendedGeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0, 4326),
                new Point(179, 1, 4326),
                new Point(178, 2, 4326),
                new Point(177, 3, 4326),
                new Point(180, 0, 4326),
            ], 4326),
        ], 4326),
        new Point(180, 0, 4326),
    ], 4326);

    // Act
    /** @var TestExtendedPlace $testPlace */
    $testPlace = TestExtendedPlace::factory()->create(['geometry_collection' => $geometryCollection])->fresh();

    // Assert
    expect($testPlace->geometry_collection)->toBeInstanceOf(ExtendedGeometryCollection::class);
    expect($testPlace->geometry_collection)->toEqual($geometryCollection);
});

it('throws exception when storing a record with regular GeometryCollection instead of the extended one', function (): void {
    // Arrange
    EloquentSpatial::useGeometryCollection(ExtendedGeometryCollection::class);
    $geometryCollection = new GeometryCollection([
        new Polygon([
            new LineString([
                new Point(180, 0),
                new Point(179, 1),
                new Point(178, 2),
                new Point(177, 3),
                new Point(180, 0),
            ]),
        ]),
        new Point(180, 0),
    ], 4326);

    // Act & Assert
    expect(function () use ($geometryCollection): void {
        TestExtendedPlace::factory()->create(['geometry_collection' => $geometryCollection]);
    })->toThrow(InvalidArgumentException::class);
});

// Edge case tests for nested GeometryCollections

it('creates deeply nested geometry collection', function (): void {
    $innerCollection = new GeometryCollection([
        new Point(1.0, 2.0),
        new Point(3.0, 4.0),
    ]);

    $middleCollection = new GeometryCollection([
        $innerCollection,
        new Point(5.0, 6.0),
    ]);

    $outerCollection = new GeometryCollection([
        $middleCollection,
        new Point(7.0, 8.0),
    ]);

    expect(count($outerCollection->getGeometries()))->toBe(2);

    /** @var GeometryCollection $level1 */
    $level1 = $outerCollection[0];
    expect($level1)->toBeInstanceOf(GeometryCollection::class);

    /** @var GeometryCollection $level2 */
    $level2 = $level1[0];
    expect($level2)->toBeInstanceOf(GeometryCollection::class);

    expect($level2[0])->toBeInstanceOf(Point::class);
});

it('creates and persists deeply nested geometry collection', function (): void {
    $innerCollection = new GeometryCollection([
        new Point(1.0, 2.0),
        new LineString([
            new Point(0.0, 0.0),
            new Point(1.0, 1.0),
        ]),
    ]);

    $outerCollection = new GeometryCollection([
        $innerCollection,
        new Point(3.0, 4.0),
    ]);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry_collection' => $outerCollection])->fresh();

    expect($testPlace->geometry_collection)->toBeInstanceOf(GeometryCollection::class);

    /** @var GeometryCollection $nested */
    $nested = $testPlace->geometry_collection[0];
    expect($nested)->toBeInstanceOf(GeometryCollection::class);
    expect($nested[0])->toBeInstanceOf(Point::class);
    expect($nested[1])->toBeInstanceOf(LineString::class);
    expect($testPlace->geometry_collection)->toEqual($outerCollection);
})->skip(fn () => isMariaDb(), 'MariaDB does not support nested geometry collections.');

it('stores a nested geometry collection as NULL on MariaDB', function (): void {
    $collection = new GeometryCollection([
        new GeometryCollection([new Point(1.0, 2.0)]),
        new Point(3.0, 4.0),
    ]);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry_collection' => $collection])->fresh();

    // Documented in the README; a MariaDB release that supports them makes this test fail.
    expect($testPlace->geometry_collection)->toBeNull();
})->skip(fn () => ! isMariaDb(), 'Only MariaDB drops nested geometry collections.');

it('preserves SRID through nested geometry collection roundtrip', function (): void {
    $collection = new GeometryCollection([
        new GeometryCollection([
            new Point(1.0, 2.0, Srid::WGS84->value),
        ], Srid::WGS84->value),
    ], Srid::WGS84->value);

    $wkb = $collection->toWkb();
    $restored = GeometryCollection::fromWkb($wkb);

    expect($restored->srid)->toBe(Srid::WGS84->value);
});

it('handles geometry collection with all geometry types', function (): void {
    $collection = new GeometryCollection([
        new Point(1.0, 2.0),
        new LineString([
            new Point(0.0, 0.0),
            new Point(1.0, 1.0),
        ]),
        new Polygon([
            new LineString([
                new Point(0.0, 0.0),
                new Point(1.0, 0.0),
                new Point(1.0, 1.0),
                new Point(0.0, 0.0),
            ]),
        ]),
    ]);

    /** @var TestPlace $testPlace */
    $testPlace = TestPlace::factory()->create(['geometry_collection' => $collection])->fresh();

    expect($testPlace->geometry_collection[0])->toBeInstanceOf(Point::class);
    expect($testPlace->geometry_collection[1])->toBeInstanceOf(LineString::class);
    expect($testPlace->geometry_collection[2])->toBeInstanceOf(Polygon::class);
});

it('leaves the collection unchanged when a geometry of the wrong type is set', function (): void {
    $lineString = new LineString([new Point(0, 0), new Point(1, 1)]);
    $polygon = new Polygon([new LineString([new Point(0, 0), new Point(1, 0), new Point(1, 1), new Point(0, 0)])]);

    // @phpstan-ignore-next-line argument.type
    expect(fn () => $lineString[1] = $polygon)
        ->toThrow(InvalidArgumentException::class, LineString::class.' must be a collection of '.Point::class);
    expect($lineString->toWkt())->toBe('LINESTRING(0 0, 1 1)');
});

it('leaves the collection unchanged when too few geometries would remain', function (): void {
    $lineString = new LineString([new Point(0, 0), new Point(1, 1)]);

    expect(function () use ($lineString): void {
        unset($lineString[0]);
    })->toThrow(InvalidArgumentException::class, LineString::class.' must contain at least 2 entries');
    expect($lineString->toWkt())->toBe('LINESTRING(0 0, 1 1)');
});

it('names the minimum number of geometries', function (): void {
    expect(fn () => new MultiPoint([]))->toThrow(InvalidArgumentException::class, MultiPoint::class.' must contain at least 1 entry')
        ->and(fn () => new LineString([new Point(0, 0)]))->toThrow(InvalidArgumentException::class, LineString::class.' must contain at least 2 entries');
});

it('throws for an offset without a geometry', function (mixed $offset): void {
    $collection = new GeometryCollection([new Point(0, 0)]);

    expect(fn () => $collection[$offset])->toThrow(OutOfBoundsException::class, GeometryCollection::class.' has no geometry at offset');
})->with([
    'after the last' => [1],
    'negative' => [-1],
    'a string' => ['a'],
    'a string with a leading zero' => ['00'],
    'a string with a sign' => ['+0'],
    'a string with a space' => [' 0'],
    'a decimal string' => ['0.0'],
]);

it('reads, sets and unsets an offset that is an integer string, as an array does', function (): void {
    $collection = new GeometryCollection([new Point(0, 0), new Point(1, 1)]);

    expect(isset($collection['1']))->toBeTrue()
        ->and(isset($collection['2']))->toBeFalse()
        ->and($collection['1'])->toEqual(new Point(1, 1));

    $collection['0'] = new Point(2, 2);
    $collection['5'] = new Point(3, 3);
    unset($collection['1']);

    expect($collection->toWkt())->toBe('GEOMETRYCOLLECTION(POINT(2 2), POINT(3 3))');
});

it('appends a geometry that is set after the last one', function (?int $offset): void {
    $collection = new GeometryCollection([new Point(0, 0)]);

    $collection[$offset] = new Point(1, 1);

    expect($collection->getGeometries()->keys()->all())->toBe([0, 1])
        ->and($collection->toJson())->toBe('{"type":"GeometryCollection","geometries":[{"type":"Point","coordinates":[0,0]},{"type":"Point","coordinates":[1,1]}]}');
})->with(['no offset' => [null], 'the next offset' => [1], 'a later offset' => [10]]);

it('replaces a geometry', function (): void {
    $collection = new GeometryCollection([new Point(0, 0), new Point(1, 1)]);

    $collection[0] = new Point(2, 2);

    expect($collection->toWkt())->toBe('GEOMETRYCOLLECTION(POINT(2 2), POINT(1 1))');
});

it('rejects an offset that is not a position', function (): void {
    $collection = new GeometryCollection([new Point(0, 0)]);

    // @phpstan-ignore-next-line offsetAssign.dimType
    expect(fn () => $collection['a'] = new Point(1, 1))->toThrow(OutOfBoundsException::class);
    expect(fn () => $collection[-1] = new Point(1, 1))->toThrow(OutOfBoundsException::class);
    expect($collection->toWkt())->toBe('GEOMETRYCOLLECTION(POINT(0 0))');
});

it('ignores unsetting an offset without a geometry', function (mixed $offset): void {
    $collection = new GeometryCollection([new Point(0, 0)]);

    unset($collection[$offset]);

    expect($collection->toWkt())->toBe('GEOMETRYCOLLECTION(POINT(0 0))');
})->with(['after the last' => [1], 'negative' => [-1], 'a string' => ['a']]);

it('moves the following geometries down when one is unset', function (): void {
    $collection = new GeometryCollection([new Point(0, 0), new Point(1, 1), new Point(2, 2)]);

    unset($collection[1]);

    expect($collection[1])->toEqual(new Point(2, 2))
        ->and($collection->getGeometries()->keys()->all())->toBe([0, 1]);
});

it('keeps its own copy of the given collection', function (): void {
    $points = collect([new Point(0, 0), new Point(1, 1)]);
    $lineString = new LineString($points);

    $points->pop();

    expect($lineString->toWkt())->toBe('LINESTRING(0 0, 1 1)');
});

it('writes the geometries as a list whatever their keys', function (): void {
    // @phpstan-ignore argument.type
    $lineString = new LineString([5 => new Point(0, 0), 'a' => new Point(1, 1)]);

    expect($lineString->toJson())->toBe('{"type":"LineString","coordinates":[[0,0],[1,1]]}');
});

it('returns the geometries as an array', function (): void {
    $collection = new GeometryCollection([new Point(0, 0)]);

    expect($collection->toArray()['geometries'])->toBe([['type' => 'Point', 'coordinates' => [0.0, 0.0]]]);
});

it('writes the properties of a feature as an object', function (): void {
    expect((new Point(0, 0))->toFeatureCollectionJson())
        ->toBe('{"type":"FeatureCollection","features":[{"type":"Feature","properties":{},"geometry":{"type":"Point","coordinates":[0,0]}}]}');
});
