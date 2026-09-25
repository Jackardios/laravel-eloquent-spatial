# Laravel Eloquent Spatial

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jackardios/laravel-eloquent-spatial.svg?style=flat-square)](https://packagist.org/packages/jackardios/laravel-eloquent-spatial)
[![Total Downloads](https://img.shields.io/packagist/dt/jackardios/laravel-eloquent-spatial.svg?style=flat-square)](https://packagist.org/packages/jackardios/laravel-eloquent-spatial)

Laravel package for working with spatial data types and functions in Eloquent.

## Supported Databases

- MySQL 5.7 / 8.0 / 8.4
- MariaDB 10.11 / 11.4
- PostgreSQL 12–17 with PostGIS 3.4+

These are the versions that the tests run on.

## Requirements

- PHP 8.3+
- Laravel 12.18+ / 13.x

For PHP 8.1 or 8.2 and Laravel 10 or 11, use version 4.x.

## Installation

```bash
composer require jackardios/laravel-eloquent-spatial
```

## Quick Start

### 1. Create a Migration

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->geometry('location', subtype: 'point')->nullable();
            $table->geometry('area', subtype: 'polygon')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
```

To restrict a column to one SRID, pass it to the column, for example `$table->geometry('location', subtype: 'point', srid: 4326)`. MySQL 8 then rejects geometries with a different SRID.

### 2. Set Up Your Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Traits\HasSpatial;

/**
 * @property Point $location
 * @property Polygon $area
 */
class Place extends Model
{
    use HasSpatial;

    protected $fillable = [
        'name',
        'location',
        'area',
    ];

    protected $casts = [
        'location' => Point::class,
        'area' => Polygon::class,
    ];
}
```

### 3. Work with Spatial Data

```php
use App\Models\Place;
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\Objects\Polygon;
use Jackardios\EloquentSpatial\Objects\LineString;
use Jackardios\EloquentSpatial\Enums\Srid;

// Create a place with a point location
// Note: Point constructor uses (longitude, latitude) order
$place = Place::create([
    'name' => 'Eiffel Tower',
    'location' => new Point(2.2945, 48.8584),
]);

// Create with SRID
$place = Place::create([
    'name' => 'Big Ben',
    'location' => new Point(-0.1246, 51.5007, Srid::WGS84),
]);

// Create with polygon area
$place = Place::create([
    'name' => 'Central Park',
    'area' => new Polygon([
        new LineString([
            new Point(-73.9819, 40.7681),
            new Point(-73.9580, 40.8006),
            new Point(-73.9498, 40.7969),
            new Point(-73.9737, 40.7644),
            new Point(-73.9819, 40.7681), // Close the ring
        ]),
    ]),
]);

// Access coordinates
echo $place->location->longitude; // 2.2945
echo $place->location->latitude;  // 48.8584
echo $place->location->srid;      // 4326 (if using WGS84)

// Convert to different formats
$place->location->toWkt();     // POINT(2.2945 48.8584)
$place->location->toJson();    // {"type":"Point","coordinates":[2.2945,48.8584]}
$place->location->toArray();   // ['type' => 'Point', 'coordinates' => [2.2945, 48.8584]]
```

## Geometry Classes

All geometry classes support creating instances from various formats:

```php
use Jackardios\EloquentSpatial\Objects\Point;

// From constructor
$point = new Point(longitude: 2.2945, latitude: 48.8584, srid: 4326);

// From WKT, or from EWKT with its SRID
$point = Point::fromWkt('POINT(2.2945 48.8584)', srid: 4326);
$point = Point::fromWkt('SRID=4326;POINT(2.2945 48.8584)');

// From a GeoJSON geometry, Feature or FeatureCollection
$point = Point::fromJson('{"type":"Point","coordinates":[2.2945,48.8584]}');

// From array
$point = Point::fromArray(['type' => 'Point', 'coordinates' => [2.2945, 48.8584]]);

// From WKB as MySQL stores it, from WKB or EWKB, or from hex EWKB as PostGIS returns it
$point = Point::fromWkb($binaryData);
```

Each method reads only its own format: `fromWkt()` does not accept GeoJSON, and `fromJson()` does not accept WKT. Z and M coordinates are read and dropped. `Factory::parse()` detects which of these formats a string is in.

### Available Geometry Types

| Class | Description |
|-------|-------------|
| `Point` | Single coordinate (longitude, latitude) |
| `LineString` | Ordered sequence of Points |
| `Polygon` | Closed shape defined by LineStrings |
| `MultiPoint` | Collection of Points |
| `MultiLineString` | Collection of LineStrings |
| `MultiPolygon` | Collection of Polygons |
| `GeometryCollection` | Mixed collection of any geometry types |
| `BoundingBox` | Rectangular bounds with antimeridian support |

### Coordinate Validation

The `Point` class validates coordinates automatically. With SRID 0 (the default) or 4326, the coordinates are degrees:

```php
// Valid coordinates
$point = new Point(180, 90);    // OK
$point = new Point(-180, -90);  // OK

// Invalid coordinates throw InvalidArgumentException
$point = new Point(200, 0);     // Error: Longitude must be between -180 and 180
$point = new Point(0, 100);     // Error: Latitude must be between -90 and 90
```

Other SRIDs have their own units, so their ranges are not checked. With any SRID, the coordinates must be finite:

```php
use Jackardios\EloquentSpatial\Enums\Srid;

$point = new Point(-8238310.24, 4970071.58, Srid::WEB_MERCATOR); // OK, metres
$point = new Point(NAN, 0);     // Error: Coordinates must be finite numbers
```

## Spatial Query Scopes

The `HasSpatial` trait provides query scopes for spatial operations:

### Distance Queries

```php
use App\Models\Place;
use Jackardios\EloquentSpatial\Objects\Point;

$referencePoint = new Point(-0.1246, 51.5007, 4326);

// Add distance to results
$places = Place::query()
    ->withDistanceSphere('location', $referencePoint)
    ->get();

foreach ($places as $place) {
    echo $place->distance; // Distance in meters
}

// Filter by distance
$nearbyPlaces = Place::query()
    ->whereDistanceSphere('location', $referencePoint, '<', 5000) // Within 5km
    ->get();

// Order by distance
$closestPlaces = Place::query()
    ->orderByDistanceSphere('location', $referencePoint)
    ->limit(10)
    ->get();
```

The operator must be one of `=`, `<`, `>`, `<=`, `>=`, `<>` and `!=`, and the direction `asc` or `desc` in any case. Anything else throws `InvalidArgumentException`, because these arguments are written into the SQL. The alias of `withDistance()` and `withDistanceSphere()` is quoted as a column name.

### Spatial Relationship Queries

```php
use Jackardios\EloquentSpatial\Objects\Polygon;

$searchArea = Polygon::fromJson('{"type":"Polygon","coordinates":[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]}');

// Find places within an area
Place::whereWithin('location', $searchArea)->get();
Place::whereNotWithin('location', $searchArea)->get();

// Find places containing a point
Place::whereContains('area', $point)->get();
Place::whereNotContains('area', $point)->get();

// Other spatial relationships
Place::whereTouches('area', $geometry)->get();
Place::whereIntersects('location', $geometry)->get();
Place::whereCrosses('route', $geometry)->get();
Place::whereDisjoint('location', $geometry)->get();
Place::whereOverlaps('area', $geometry)->get();
Place::whereEquals('location', $point)->get();

// Filter by SRID
Place::whereSrid('location', '=', 4326)->get();

// Get centroid
Place::query()
    ->withCentroid('area')
    ->withCasts(['centroid' => Point::class])
    ->get();
```

## BoundingBox

The `BoundingBox` class represents rectangular geographic bounds:

```php
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\Objects\BoundingBox;
use Jackardios\EloquentSpatial\Objects\Point;

// Create from corner points
$bbox = new BoundingBox(
    leftBottom: new Point(-74.0, 40.7),
    rightTop: new Point(-73.9, 40.8)
);

// Create from geometry
$bbox = BoundingBox::fromGeometry($polygon);

// Create from points with padding; a single point gives a box of zero size without padding
$bbox = BoundingBox::fromPoints($pointsArray, minPadding: 0.01);

// Access bounds (copies of the corners)
$bbox->getLeftBottom();  // Bottom-left Point
$bbox->getRightTop();    // Top-right Point

// Convert to geometry, optionally with an SRID
$polygon = $bbox->toPolygon();
$geometry = $bbox->toGeometry(Srid::WGS84); // Returns MultiPolygon if crosses antimeridian

// Check if crosses antimeridian (dateline)
$bbox->crossesAntimeridian(); // true/false

// Serialize
$bbox->toArray();  // ['left' => ..., 'bottom' => ..., 'right' => ..., 'top' => ...]
$bbox->toJson();
```

### BoundingBox as Model Attribute

```php
use Jackardios\EloquentSpatial\Objects\BoundingBox;

class Region extends Model
{
    use HasSpatial;

    protected $casts = [
        // Store as geometry column
        'bounds' => BoundingBox::class,

        // Or as a geometry column with SRID 4326
        'bounds' => BoundingBox::class . ':geometry,4326',

        // Or store as JSON
        'bounds' => BoundingBox::class . ':json',
    ];
}
```

The geometry is stored with the default SRID unless the cast names one. On MySQL 8, a column with an SRID accepts only geometries with that SRID.

## SRID Support

Spatial Reference Identifiers define coordinate systems:

```php
use Jackardios\EloquentSpatial\Enums\Srid;
use Jackardios\EloquentSpatial\EloquentSpatial;

// Available SRID constants
Srid::WGS84;        // 4326 - GPS coordinates
Srid::WEB_MERCATOR; // 3857 - Web maps (Google Maps, etc.)

// Set default SRID for all geometries
EloquentSpatial::setDefaultSrid(Srid::WGS84);
```

## Extending Geometry Classes

### Using Macros

```php
use Jackardios\EloquentSpatial\Objects\Geometry;

// Register in a service provider
Geometry::macro('distanceToKm', function (Point $other): float {
    /** @var Geometry $this */
    // Custom distance calculation
});

// Usage
$point->distanceToKm($otherPoint);
```

### Custom Geometry Classes

```php
use Jackardios\EloquentSpatial\Objects\Point;
use Jackardios\EloquentSpatial\EloquentSpatial;

class CustomPoint extends Point
{
    public function toLatLngArray(): array
    {
        return ['lat' => $this->latitude, 'lng' => $this->longitude];
    }
}

// Register in service provider
EloquentSpatial::usePoint(CustomPoint::class);
```

Parsed and database values are then created as `CustomPoint`. A `Point::class` cast accepts it, and GeoJSON and WKB write it as a `Point`.

### Long-running processes (Octane, queue workers)

The classes registered with `EloquentSpatial::use*()`, the default SRID and macros are static, so they are shared by all requests that a process handles. Set them once in the `boot()` method of a service provider, never per request.

## Dirty Checks

The geometry and bounding box casts compare values themselves, so models detect changes correctly without the `HasSpatial` trait. A change of only the SRID or only the geometry type, such as a `Point` replaced by a `MultiPoint` with the same coordinates, is saved.

## Limitations

- **MariaDB** does not support nested geometry collections: `ST_GeomFromText()` returns `NULL` for them, so such a value is saved as `NULL`.
- **MySQL 8** reads WKT with a geographic SRID such as 4326 as latitude first. The package adds `'axis-order=long-lat'` to the SQL it generates. If you assign a raw expression yourself, add it too:

```php
$place->location = DB::raw("ST_GeomFromText('POINT(2.2945 48.8584)', 4326, 'axis-order=long-lat')");
```

- **Geometries nested more than 64 levels deep** are not read. Such values throw `InvalidArgumentException`, because reading them could crash PHP.

## API Reference

For complete API documentation, see [API.md](API.md).

## Upgrading

See [UPGRADE.md](UPGRADE.md) for upgrade instructions from previous versions.

## Development

```bash
# Start database containers
docker-compose up -d

# Run tests
composer pest:mysql
composer pest:mariadb
composer pest:postgres

# Static analysis
composer phpstan

# Code formatting
composer pint
```

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
