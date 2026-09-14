--TEST--
soda quality --help
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$_SERVER['argv'] = ['soda', 'quality', '--help'];

require __DIR__ . '/../../soda';
--EXPECTF--
%ADescription:
  Analyse code quality and check against configured thresholds

Usage:
  quality [options] [--] [<path>...]

Arguments:
%Apath%A

Options:
%A--config%A
%A--report-json%AWrite quality report to JSON file%A
