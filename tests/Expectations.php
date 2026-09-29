<?php

use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;

expect()->extend('toBeOnPostgres', function (mixed $value) {
    return $this->when(DB::connection() instanceof PostgresConnection, fn () => $this->toBe($value));
});

expect()->extend('toBeOnMysql', function (mixed $value) {
    return $this->when(! (DB::connection() instanceof PostgresConnection), fn () => $this->toBe($value));
});
