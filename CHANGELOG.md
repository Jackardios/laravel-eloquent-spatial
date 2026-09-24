# Changelog

All notable changes to `jackardios/laravel-eloquent-spatial` are documented in this file.

This package is a fork of [matanyadaev/laravel-eloquent-spatial](https://github.com/MatanYadaev/laravel-eloquent-spatial).
For the history before the fork, see the [upstream changelog](https://github.com/MatanYadaev/laravel-eloquent-spatial/blob/master/CHANGELOG.md).

## Unreleased (4.1.0)

### Added

- Laravel 13 support.

### Fixed

- The service provider no longer extends Laravel's `DatabaseServiceProvider`. Registering it registered the database services a second time and replaced the already-resolved `db` manager.
- MySQL axis-order support is detected once per database connection instead of for every geometry, and a `MariaDbConnection` (Laravel 11+) no longer opens a connection just for this check.

### Documentation

- `UPGRADE.md` no longer claims that `fromWkt()` and `fromWkb()` skip coordinate validation; they validate like the `Point` constructor.
- README: added Laravel 10 migration syntax and database limitations.

## v4.0.0 - 2026-01-11

- Namespace changed from `MatanYadaev\EloquentSpatial` to `Jackardios\EloquentSpatial`.
- `Point` validates longitude (-180..180) and latitude (-90..90).
- `BoundingBox` with antimeridian support, usable as an Eloquent cast (`geometry` or `json` storage).
- `whereOverlaps` scope.

See [UPGRADE.md](UPGRADE.md#upgrading-from-v3x-to-v40) for details.
