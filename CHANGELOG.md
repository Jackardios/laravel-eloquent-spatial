# Changelog

All notable changes to `jackardios/laravel-eloquent-spatial` are documented in this file.

This package is a fork of [matanyadaev/laravel-eloquent-spatial](https://github.com/MatanYadaev/laravel-eloquent-spatial).
For the history before the fork, see the [upstream changelog](https://github.com/MatanYadaev/laravel-eloquent-spatial/blob/master/CHANGELOG.md).

## Unreleased

### Changed

- MySQL axis-order support is detected for every geometry again, as in 4.0. The cache from 4.1.0 saved nothing: PDO keeps the server version, so reading it sends no query.

## v5.0.2 - 2026-09-30

### Changed

- `withCentroid()` selects all columns too, as `withDistance()` does, unless the query selects columns. The models had only the centroid.
- WKB is read in one pass instead of being checked first, so reading a point from the database is about 30% faster. Invalid WKB can throw with another message, such as the error of the first invalid geometry. WKB whose bytes also look like the MySQL format, such as `POINT(4.778309780138253E-299 0)`, is read instead of throwing.

### Fixed

- Reading WKT took about 400 bytes of memory for every number, comma and parenthesis, because brick/geo splits the whole value into tokens first: a line of 100,000 points took 175 MB, and 300 KB of invalid WKT exceeded a 128 MB memory limit. WKT is now read by the package, the line takes 15 MB, and reading WKT is about 3 times faster, as fast as in 4.x. The same WKT is accepted, but the error messages have changed.
- A geometry cast attribute set to an expression that only contains `ST_GeomFromText()`, such as `ST_Centroid(ST_GeomFromText(...))`, was read as the inner geometry: the change was not saved if that geometry was already stored.
- The spatial scopes no longer run the model's global scopes, once for every geometry or column they write into the SQL, which also made building such a query slower.
- `BoundingBox::fromPoints()` merged longitudes that differ only after the 14th digit, so the box could miss a point. The result depended on the `precision` setting.
- A clone of a `GeometryCollection`, `LineString` or another collection shared the list of geometries with the original, so adding, replacing or removing a geometry of the clone changed the original too.
- A GeoJSON FeatureCollection of only points, only lines or only polygons, single or multi, is read as a `MultiPoint`, `MultiLineString` or `MultiPolygon` again, as in 4.x, instead of a `GeometryCollection`. So `MultiPolygon::fromJson()` reads a FeatureCollection of polygons again.
- A FeatureCollection with several features could have geometries nested 65 levels deep, one more than the limit.
- The bounding box cast in the json format throws `InvalidArgumentException` instead of a `TypeError` for JSON that is not an object, such as `null`.

### Documentation

- The query examples in the README and API.md work on every supported database. They mixed SRID 0 and 4326, which MySQL 8 and PostGIS reject, and showed distances that only MySQL 8 returns.
- UPGRADE.md: corrected what 4.x did with WKB and with geometries of the wrong type, the range query for MySQL 8, and the effect of `serialize_precision` on `toWkt()`.

## v5.0.1 - 2026-09-25

### Fixed

- Saving a model with a changed 1000-point geometry was 8 times slower than in 4.x, because the dirty checks read the WKT of the geometry back with brick/geo. It is as fast as in 4.x again. `toWkt()` is also about 1.3 times faster for coordinates with few digits, such as GPS coordinates, but 1.5 times slower for coordinates that need all 17 digits.
- `Factory::parse()` reads WKB in the MySQL format whatever its SRID. For 1686 of the 8500 SRIDs that PostGIS knows, such as 2154, 3395 and the northern UTM zones 32601, 32602 and 32609 to 32635, it tried to read the value as WKT or GeoJSON because the SRID comes first.
- `GeometryCollection` and its subclasses accept a string that is an integer, such as `'1'`, as an offset again, as in 4.x and as an array does. In 5.0.0 reading it threw `OutOfBoundsException`.

## v5.0.0 - 2026-09-25

See [UPGRADE.md](UPGRADE.md#upgrading-from-v4x-to-v50) for the details of every change.

### Changed

- Requires PHP 8.3+ and Laravel 12.18+ or 13.x.
- WKT and GeoJSON are read with brick/geo instead of geoPHP, which is unmaintained and emits deprecations on PHP 8.5. WKB is read and written by the package; reading geometries from the database is faster than in 4.x.
- `fromWkt()`, `fromJson()` and `fromWkb()` read only their own format. `Factory::parse()` detects WKT, EWKT, GeoJSON, WKB, EWKB and hex WKB or EWKB, and no longer reads KML, GPX, GeoRSS or geohashes.
- `fromWkt()` and `Factory::parse()` read the SRID of EWKT, and `Factory::parse()` reads the SRID of EWKB and MySQL WKB. The geometries inside a parsed geometry have its SRID.
- `Point` checks the longitude and latitude ranges only for SRID 0 and 4326.
- `toWkt()` writes coordinates without rounding them to the `precision` setting.
- A `BoundingBox` can have zero height, and `getLeftBottom()` and `getRightTop()` return copies.
- Dirty checks are done by the casts (`ComparesCastableAttributes`) instead of `HasSpatial::originalIsEquivalent()`.
- Geometry casts accept subclasses of the same geometry type. Subclasses are written to GeoJSON and WKB as their geometry type.
- `GeometryCollection` stays a list and stays valid when it is changed as an array. A missing offset throws `OutOfBoundsException`.
- `toFeatureCollectionJson()` writes `"properties":{}`, and `GeometryCollection::toArray()` returns the geometries as an array.

### Added

- An SRID option for the bounding box cast in the geometry format, `BoundingBox::class.':geometry,4326'`, and an optional SRID for `BoundingBox::toPolygon()` and `toGeometry()`.

### Fixed

- **Security:** the operators, order directions and aliases of the distance and SRID scopes are no longer written into the SQL unchecked, which allowed SQL injection. Also fixed in 4.1.0.
- `NAN` and `INF` coordinates are rejected.
- `BoundingBox::fromPoints()` no longer loops forever for an infinite or very large padding, and returns the whole longitude range for a padding of 360 or more.
- Deeply nested geometries and invalid WKB no longer crash PHP or allocate large amounts of memory; nesting is limited to 64 levels.
- A bounding box wider than 180 degrees stored as a geometry is read back correctly, and an unchanged bounding box is no longer written on every save.
- A change of only the SRID or the geometry type is saved.
- `toSqlExpression()` accepts only WKT characters instead of escaping the WKT with `addslashes()`.
- Hex WKB with the MySQL SRID prefix is read with the right coordinates.

### Removed

- The service provider, the Doctrine DBAL types, `HasSpatial::originalIsEquivalent()`, `Factory::loadGeoPhp()`, the protected `Factory::createFromGeometry()` and the `phayes/geophp` dependency.

## v4.1.0 - 2026-09-25

### Security

- The `$operator` of `whereDistance`, `whereDistanceSphere` and `whereSrid`, the `$direction` of `orderByDistance` and `orderByDistanceSphere`, and the `$alias` of `withDistance` and `withDistanceSphere` were written into the SQL unchecked, which allowed SQL injection when these values came from user input. Operators other than `=`, `<`, `>`, `<=`, `>=`, `<>` and `!=`, and directions other than `asc` and `desc`, now throw `InvalidArgumentException`, and the alias is quoted as a column name.

### Added

- Laravel 13 support.

### Fixed

- PHP 8.5: parsing and `toWkb()` no longer fail with `Class "Point" not found` when the application turns deprecations into exceptions. geoPHP's compile-time deprecations are now silenced while it loads.
- The service provider no longer extends Laravel's `DatabaseServiceProvider`. Registering it registered the database services a second time and replaced the already-resolved `db` manager.
- MySQL axis-order support is detected once per database connection instead of for every geometry.

### Documentation

- `UPGRADE.md` no longer claims that `fromWkt()` and `fromWkb()` skip coordinate validation; they validate like the `Point` constructor.
- README: added Laravel 10 migration syntax and database limitations.

## v4.0.0 - 2026-01-11

- Namespace changed from `MatanYadaev\EloquentSpatial` to `Jackardios\EloquentSpatial`.
- `Point` validates longitude (-180..180) and latitude (-90..90).
- `BoundingBox` with antimeridian support, usable as an Eloquent cast (`geometry` or `json` storage).
- `whereOverlaps` scope.

See [UPGRADE.md](UPGRADE.md#upgrading-from-v3x-to-v40) for details.
