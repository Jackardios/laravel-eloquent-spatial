# API

## Available Geometry Classes

All geometry classes are in the `Jackardios\EloquentSpatial\Objects` namespace.

* `Point(float $longitude, float $latitude, int|Srid|null $srid = null)` - [MySQL Point](https://dev.mysql.com/doc/refman/8.0/en/gis-class-point.html)
* `MultiPoint(Point[] | Collection<Point> $geometries, int|Srid|null $srid = null)` - [MySQL MultiPoint](https://dev.mysql.com/doc/refman/8.0/en/gis-class-multipoint.html)
* `LineString(Point[] | Collection<Point> $geometries, int|Srid|null $srid = null)` - [MySQL LineString](https://dev.mysql.com/doc/refman/8.0/en/gis-class-linestring.html)
* `MultiLineString(LineString[] | Collection<LineString> $geometries, int|Srid|null $srid = null)` - [MySQL MultiLineString](https://dev.mysql.com/doc/refman/8.0/en/gis-class-multilinestring.html)
* `Polygon(LineString[] | Collection<LineString> $geometries, int|Srid|null $srid = null)` - [MySQL Polygon](https://dev.mysql.com/doc/refman/8.0/en/gis-class-polygon.html)
* `MultiPolygon(Polygon[] | Collection<Polygon> $geometries, int|Srid|null $srid = null)` - [MySQL MultiPolygon](https://dev.mysql.com/doc/refman/8.0/en/gis-class-multipolygon.html)
* `GeometryCollection(Geometry[] | Collection<Geometry> $geometries, int|Srid|null $srid = null)` - [MySQL GeometryCollection](https://dev.mysql.com/doc/refman/8.0/en/gis-class-geometrycollection.html)

### Coordinate Order

**Important:** The `Point` constructor uses `(longitude, latitude)` order (GeoJSON convention), not `(latitude, longitude)`.

### Coordinate Validation

The `Point` constructor validates coordinates:
- Both must be finite numbers (not `NAN` or `INF`)
- With SRID 0 or 4326, the coordinates are degrees:
  - Longitude: -180 to 180
  - Latitude: -90 to 90

Invalid coordinates throw `InvalidArgumentException`.

### Static Factory Methods

Geometry classes can be created using these static methods. Each accepts only its own format, and throws `InvalidArgumentException` for invalid input or a geometry of another class:

* `fromArray(array $geometry, int|Srid|null $srid = null)` - Creates from a [GeoJSON](https://en.wikipedia.org/wiki/GeoJSON) array
* `fromJson(string $geoJson, int|Srid|null $srid = null)` - Creates from a [GeoJSON](https://en.wikipedia.org/wiki/GeoJSON) geometry, Feature, or FeatureCollection (its only geometry, or a `GeometryCollection` of its geometries)
* `fromWkt(string $wkt, int|Srid|null $srid = null)` - Creates from [WKT](https://en.wikipedia.org/wiki/Well-known_text_representation_of_geometry), or EWKT such as `SRID=4326;POINT(1 2)`. Without `$srid`, the SRID is read from EWKT, and is the default SRID for WKT
* `fromWkb(string $wkb)` - Creates from [WKB](https://en.wikipedia.org/wiki/Well-known_text_representation_of_geometry#Well-known_binary) as MySQL stores it (a 4-byte SRID followed by WKB), WKB, or EWKB, binary or hex. The SRID is read from the MySQL format and EWKB, and is 0 for WKB

Z and M coordinates are dropped. Geometries nested more than 64 levels deep are not read.

`Jackardios\EloquentSpatial\Factory::parse(string $value)` detects which of these formats a string is in, and reads the SRID from EWKT, EWKB and the MySQL format.

## Geometry Instance Methods

* `toArray()` - Serializes to a GeoJSON associative array
* `toJson()` - Serializes to a GeoJSON string
* `toFeatureCollectionJson()` - Serializes to a GeoJSON FeatureCollection string
* `toWkt()` - Serializes to WKT string, with every coordinate as the shortest number that reads back as the same float
* `toWkb()` - Serializes to WKB as MySQL stores it: the SRID as a little-endian 32-bit integer, followed by little-endian WKB
* `getCoordinates()` - Returns the coordinates array
* `toSqlExpression(ConnectionInterface $connection)` - Converts to SQL expression for database queries
* `toBoundingBox(float $minPadding = 0)` - Creates a BoundingBox from the geometry

### GeometryCollection Additional Methods

* `getGeometries()` - Returns the geometries collection
* `getPoints()` - Returns all Point objects (flattened from nested geometries)

`GeometryCollection` implements `ArrayAccess`:

```php
use Jackardios\EloquentSpatial\Objects\GeometryCollection;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Objects\LineString;

$geometryCollection = new GeometryCollection([
    new Polygon([
        new LineString([
            new Point(0, 0),
            new Point(1, 0),
            new Point(1, 1),
            new Point(0, 1),
            new Point(0, 0),
        ]),
    ]),
    new Point(0, 0),
]);

echo $geometryCollection->getGeometries()[1]->latitude; // 0
// or access as an array:
echo $geometryCollection[1]->latitude; // 0
```

The collection stays a list and stays valid:

* Reading an offset that does not exist throws `OutOfBoundsException`.
* `$collection[] = $geometry`, or an offset after the last geometry, appends the geometry.
* `unset($collection[$offset])` moves the following geometries down by one.
* A geometry of the wrong type, or an `unset()` that would leave too few geometries, throws `InvalidArgumentException` and leaves the collection unchanged.

The constructor copies the given array or collection, so later changes to it do not change the geometry.

## BoundingBox

The `BoundingBox` class represents rectangular geographic bounds with support for antimeridian crossing.

### Constructor

```php
new BoundingBox(Point $leftBottom, Point $rightTop)
```

**Validation:** Longitudes must be between -180 and 180 and latitudes between -90 and 90, whatever the SRID of the points. The latitude of `$leftBottom` must not be greater than the latitude of `$rightTop`, so a box can have zero height or width. Longitude can wrap around the antimeridian.

The box keeps copies of the points, so later changes to them do not change the box.

### Static Factory Methods

* `fromGeometry(Geometry $geometry, float $minPadding = 0)` - Creates from any geometry
* `fromPoints(array|Collection $points, float $minPadding = 0)` - Creates from a collection of points. `$minPadding` must be finite and not negative; a padding of 360 or more gives the whole longitude range
* `fromArray(array $array)` - Creates from associative array with keys: `left`, `bottom`, `right`, `top`

### Instance Methods

* `getLeftBottom()` - Returns a copy of the bottom-left corner Point
* `getRightTop()` - Returns a copy of the top-right corner Point
* `crossesAntimeridian()` - Returns `true` if the box crosses the 180°/-180° longitude line
* `toPolygon(int|Srid|null $srid = null)` - Converts to Polygon (throws if crosses antimeridian), with the given or the default SRID
* `toGeometry(int|Srid|null $srid = null)` - Converts to Polygon or MultiPolygon (handles antimeridian), with the given or the default SRID
* `toArray()` - Returns `['left' => float, 'bottom' => float, 'right' => float, 'top' => float]`
* `toJson()` - Serializes to JSON string

### Eloquent Cast

`BoundingBox` can be used as an Eloquent cast with two storage formats:

```php
protected $casts = [
    // Store as geometry column (Polygon/MultiPolygon) with the default SRID
    'bounds' => BoundingBox::class,

    // Store as geometry column with SRID 4326
    'bounds' => BoundingBox::class . ':geometry,4326',

    // Store as JSON column
    'bounds' => BoundingBox::class . ':json',
];
```

A box stored as a geometry is read back from the corners of the polygon or polygons that `toGeometry()` writes, so a box wider than 180° reads back unchanged.

### Example

```php
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Point;

// Standard bounding box
$bbox = new BoundingBox(
    leftBottom: new Point(-74.0, 40.7),
    rightTop: new Point(-73.9, 40.8)
);

// Antimeridian-crossing bounding box (Pacific Ocean)
$pacificBbox = new BoundingBox(
    leftBottom: new Point(170, -10),
    rightTop: new Point(-170, 10)
);

$pacificBbox->crossesAntimeridian(); // true
$pacificBbox->toGeometry();          // Returns MultiPolygon
```

## Available Enums

Spatial reference identifiers (SRID) identify the coordinate system.

```php
use Jackardios\EloquentSpatial\Enums\Srid;
```

| Identifier           | Value  | Description                                                                         |
|----------------------|--------|-------------------------------------------------------------------------------------|
| `Srid::WGS84`        | `4326` | [Geographic coordinate system](https://epsg.org/crs_4326/WGS-84.html)               |
| `Srid::WEB_MERCATOR` | `3857` | [Mercator coordinate system](https://epsg.org/crs_3857/WGS-84-Pseudo-Mercator.html) |

### Setting Default SRID

```php
use Jackardios\EloquentSpatial\EloquentSpatial;
use Jackardios\EloquentSpatial\Enums\Srid;

// In a service provider
EloquentSpatial::setDefaultSrid(Srid::WGS84);
```

## Spatial Query Scopes

Add the `HasSpatial` trait to your model to use these scopes:

```php
use Jackardios\EloquentSpatial\Traits\HasSpatial;

class Place extends Model
{
    use HasSpatial;
}
```

The `$operator` of `whereDistance`, `whereDistanceSphere` and `whereSrid` must be one of `=`, `<`, `>`, `<=`, `>=`, `<>` and `!=`. The `$direction` of `orderByDistance` and `orderByDistanceSphere` must be `asc` or `desc`, in any case. Other values throw `InvalidArgumentException`. Aliases are quoted as column names.

### Table of Contents

* [withDistance](#withdistance)
* [whereDistance](#wheredistance)
* [orderByDistance](#orderbydistance)
* [withDistanceSphere](#withdistancesphere)
* [whereDistanceSphere](#wheredistancesphere)
* [orderByDistanceSphere](#orderbydistancesphere)
* [whereWithin](#wherewithin)
* [whereNotWithin](#wherenotwithin)
* [whereContains](#wherecontains)
* [whereNotContains](#wherenotcontains)
* [whereTouches](#wheretouches)
* [whereIntersects](#whereintersects)
* [whereCrosses](#wherecrosses)
* [whereDisjoint](#wheredisjoint)
* [whereOverlaps](#whereoverlaps)
* [whereEquals](#whereequals)
* [whereSrid](#wheresrid)
* [withCentroid](#withcentroid)

---

### withDistance

Retrieves the distance between 2 geometry objects. Uses [ST_Distance](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-distance).

| Parameter           | Type                              | Default      |
|---------------------|-----------------------------------|--------------|
| `$column`           | `Expression\|Geometry\|string`    |              |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |              |
| `$alias`            | `string`                          | `'distance'` |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;

Place::create(['location' => new Point(0, 0, 4326)]);

$placeWithDistance = Place::query()
    ->withDistance('location', new Point(1, 1, 4326))
    ->first();

echo $placeWithDistance->distance; // 156897.79947260793

// With custom alias:
$placeWithDistance = Place::query()
    ->withDistance('location', new Point(1, 1, 4326), 'distance_in_meters')
    ->first();

echo $placeWithDistance->distance_in_meters; // 156897.79947260793
```
</details>

---

### whereDistance

Filters records by distance. Uses [ST_Distance](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-distance).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |
| `$operator`         | `string`                          |
| `$value`            | `int\|float`                      |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;

Place::create(['location' => new Point(0, 0, 4326)]);
Place::create(['location' => new Point(50, 50, 4326)]);

$count = Place::query()
    ->whereDistance('location', new Point(1, 1, 4326), '<', 160000)
    ->count();

echo $count; // 1
```
</details>

---

### orderByDistance

Orders records by distance. Uses [ST_Distance](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-distance).

| Parameter           | Type                              | Default |
|---------------------|-----------------------------------|---------|
| `$column`           | `Expression\|Geometry\|string`    |         |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |         |
| `$direction`        | `string`                          | `'asc'` |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;

Place::create(['name' => 'first', 'location' => new Point(0, 0, 4326)]);
Place::create(['name' => 'second', 'location' => new Point(50, 50, 4326)]);

$places = Place::query()
    ->orderByDistance('location', new Point(1, 1, 4326), 'desc')
    ->get();

echo $places[0]->name; // second
echo $places[1]->name; // first
```
</details>

---

### withDistanceSphere

Retrieves the spherical distance between 2 geometry objects. Uses [ST_Distance_Sphere](https://dev.mysql.com/doc/refman/8.0/en/spatial-convenience-functions.html#function_st-distance-sphere).

| Parameter           | Type                              | Default      |
|---------------------|-----------------------------------|--------------|
| `$column`           | `Expression\|Geometry\|string`    |              |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |              |
| `$alias`            | `string`                          | `'distance'` |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;

Place::create(['location' => new Point(0, 0, 4326)]);

$placeWithDistance = Place::query()
    ->withDistanceSphere('location', new Point(1, 1, 4326))
    ->first();

echo $placeWithDistance->distance; // 157249.59776850493

// With custom alias:
$placeWithDistance = Place::query()
    ->withDistanceSphere('location', new Point(1, 1, 4326), 'distance_in_meters')
    ->first();

echo $placeWithDistance->distance_in_meters; // 157249.59776850493
```
</details>

---

### whereDistanceSphere

Filters records by spherical distance. Uses [ST_Distance_Sphere](https://dev.mysql.com/doc/refman/8.0/en/spatial-convenience-functions.html#function_st-distance-sphere).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |
| `$operator`         | `string`                          |
| `$value`            | `int\|float`                      |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;

Place::create(['location' => new Point(0, 0, 4326)]);
Place::create(['location' => new Point(50, 50, 4326)]);

$count = Place::query()
    ->whereDistanceSphere('location', new Point(1, 1, 4326), '<', 160000)
    ->count();

echo $count; // 1
```
</details>

---

### orderByDistanceSphere

Orders records by spherical distance. Uses [ST_Distance_Sphere](https://dev.mysql.com/doc/refman/8.0/en/spatial-convenience-functions.html#function_st-distance-sphere).

| Parameter           | Type                              | Default |
|---------------------|-----------------------------------|---------|
| `$column`           | `Expression\|Geometry\|string`    |         |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |         |
| `$direction`        | `string`                          | `'asc'` |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;

Place::create(['name' => 'first', 'location' => new Point(0, 0, 4326)]);
Place::create(['name' => 'second', 'location' => new Point(50, 50, 4326)]);

$places = Place::query()
    ->orderByDistanceSphere('location', new Point(1, 1, 4326), 'desc')
    ->get();

echo $places[0]->name; // second
echo $places[1]->name; // first
```
</details>

---

### whereWithin

Filters records where geometry is within another geometry. Uses [ST_Within](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-within).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['location' => new Point(0, 0, 4326)]);

$exists = Place::query()
    ->whereWithin('location', Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}'))
    ->exists();

echo $exists; // true
```
</details>

---

### whereNotWithin

Filters records where geometry is NOT within another geometry. Uses [ST_Within](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-within).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['location' => new Point(0, 0, 4326)]);

$exists = Place::query()
    ->whereNotWithin('location', Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}'))
    ->exists();

echo $exists; // false
```
</details>

---

### whereContains

Filters records where geometry contains another geometry. Uses [ST_Contains](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-contains).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['area' => Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}')]);

$exists = Place::query()
    ->whereContains('area', new Point(0, 0, 4326))
    ->exists();

echo $exists; // true
```
</details>

---

### whereNotContains

Filters records where geometry does NOT contain another geometry. Uses [ST_Contains](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-contains).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['area' => Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}')]);

$exists = Place::query()
    ->whereNotContains('area', new Point(0, 0, 4326))
    ->exists();

echo $exists; // false
```
</details>

---

### whereTouches

Filters records where geometries touch. Uses [ST_Touches](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-touches).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['location' => new Point(0, 0, 4326)]);

$exists = Place::query()
    ->whereTouches('location', Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[0,-1],[0,0],[-1,0],[-1,-1]]]}'))
    ->exists();

echo $exists; // true
```
</details>

---

### whereIntersects

Filters records where geometries intersect. Uses [ST_Intersects](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-intersects).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['location' => new Point(0, 0, 4326)]);

$exists = Place::query()
    ->whereIntersects('location', Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}'))
    ->exists();

echo $exists; // true
```
</details>

---

### whereCrosses

Filters records where geometries cross. Uses [ST_Crosses](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-crosses).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['line_string' => LineString::fromJson('{"type":"LineString","coordinates":[[0,0],[2,0]]}')]);

$exists = Place::query()
    ->whereCrosses('line_string', Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}'))
    ->exists();

echo $exists; // true
```
</details>

---

### whereDisjoint

Filters records where geometries are disjoint (do not intersect). Uses [ST_Disjoint](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-disjoint).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['location' => new Point(0, 0, 4326)]);

$exists = Place::query()
    ->whereDisjoint('location', Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[-0.5,-1],[-0.5,-0.5],[-1,-0.5],[-1,-1]]]}'))
    ->exists();

echo $exists; // true
```
</details>

---

### whereOverlaps

Filters records where geometries overlap. Uses [ST_Overlaps](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-overlaps).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Polygon;

Place::create(['area' => Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}')]);

$exists = Place::query()
    ->whereOverlaps('area', Polygon::fromJson('{"type":"Polygon","coordinates":[[[0,0],[2,0],[2,2],[0,2],[0,0]]]}'))
    ->exists();

echo $exists; // true
```
</details>

---

### whereEquals

Filters records where geometries are spatially equal. Uses [ST_Equals](https://dev.mysql.com/doc/refman/8.0/en/spatial-relation-functions-object-shapes.html#function_st-equals).

| Parameter           | Type                              |
|---------------------|-----------------------------------|
| `$column`           | `Expression\|Geometry\|string`    |
| `$geometryOrColumn` | `Expression\|Geometry\|string`    |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;

Place::create(['location' => new Point(0, 0, 4326)]);

$exists = Place::query()
    ->whereEquals('location', new Point(0, 0, 4326))
    ->exists();

echo $exists; // true
```
</details>

---

### whereSrid

Filters records by SRID. Uses [ST_SRID](https://dev.mysql.com/doc/refman/8.0/en/gis-general-property-functions.html#function_st-srid).

| Parameter   | Type                              |
|-------------|-----------------------------------|
| `$column`   | `Expression\|Geometry\|string`    |
| `$operator` | `string`                          |
| `$value`    | `int`                             |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;

Place::create(['location' => new Point(0, 0, 4326)]);

$exists = Place::query()
    ->whereSrid('location', '=', 4326)
    ->exists();

echo $exists; // true
```
</details>

---

### withCentroid

Retrieves the centroid of a geometry. Uses [ST_Centroid](https://dev.mysql.com/doc/refman/8.0/en/gis-polygon-property-functions.html#function_st-centroid).

| Parameter | Type                              | Default      |
|-----------|-----------------------------------|--------------|
| `$column` | `Expression\|Geometry\|string`    |              |
| `$alias`  | `string`                          | `'centroid'` |

<details><summary>Example</summary>

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;

$polygon = Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}');
Place::create(['area' => $polygon]);

$placeWithCentroid = Place::query()
    ->withCentroid('area')
    ->withCasts(['centroid' => Point::class]) // Important: cast to Point
    ->first();

echo $placeWithCentroid->centroid->longitude; // 0
echo $placeWithCentroid->centroid->latitude;  // 0

// With custom alias:
$placeWithCentroid = Place::query()
    ->withCentroid('area', 'center_point')
    ->withCasts(['center_point' => Point::class])
    ->first();

echo $placeWithCentroid->center_point->longitude; // 0
```
</details>
