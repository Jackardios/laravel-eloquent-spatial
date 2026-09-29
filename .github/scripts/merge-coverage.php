<?php

/*
 * Merges the PHP coverage files of the database-specific test runs and fails below 100% line coverage.
 *
 * Usage: php .github/scripts/merge-coverage.php <coverage.cov>...
 */

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Thresholds;

require __DIR__.'/../../vendor/autoload.php';

$merged = null;

foreach (array_slice($argv, 1) as $path) {
    $coverage = include $path;

    if (! $coverage instanceof CodeCoverage) {
        fwrite(STDERR, "$path is not a PHP coverage file.\n");
        exit(1);
    }

    $merged === null ? $merged = $coverage : $merged->merge($coverage);
}

if ($merged === null) {
    fwrite(STDERR, "No coverage files given.\n");
    exit(1);
}

echo (new Text(Thresholds::default(), true, false))->process($merged);

$report = $merged->getReport();

if ($report->numberOfExecutedLines() !== $report->numberOfExecutableLines()) {
    fwrite(STDERR, sprintf(
        "Line coverage is %d/%d; every line must be covered by at least one database.\n",
        $report->numberOfExecutedLines(),
        $report->numberOfExecutableLines(),
    ));
    exit(1);
}
