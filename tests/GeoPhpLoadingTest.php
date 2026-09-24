<?php

use Jackardios\EloquentSpatial\Objects\Point;
use Symfony\Component\Process\Process;

it('parses and serializes when deprecations are turned into exceptions', function (): void {
    // A fresh process, because geoPHP has to be loaded for the first time under the strict handler.
    $script = <<<'PHP'
        require 'vendor/autoload.php';

        set_error_handler(static function (int $level, string $message): never {
            throw new ErrorException($message, 0, $level);
        });

        $point = Jackardios\EloquentSpatial\Objects\Point::fromWkt('POINT(1 2)', 4326);
        echo $point->toWkt(), ' ', bin2hex(Jackardios\EloquentSpatial\Objects\Point::fromWkb($point->toWkb())->toWkb());
        PHP;

    $process = new Process([PHP_BINARY, '-r', $script], dirname(__DIR__));
    $process->run();

    // Only the exit code and the output: unrelated notices (e.g. from Xdebug) may appear on stderr.
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput())
        ->and($process->getOutput())->toBe('POINT(1 2) e61000000101000000000000000000f03f0000000000000040');
});

it('restores the application error handler after loading geoPHP', function (): void {
    $handler = static fn (): bool => false;
    set_error_handler($handler);

    try {
        Point::fromWkt('POINT(1 2)');
        (new Point(1, 2))->toWkb();
    } finally {
        $active = set_error_handler(null);
        restore_error_handler();
        restore_error_handler();
    }

    expect($active)->toBe($handler);
});
