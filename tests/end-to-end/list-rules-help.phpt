--TEST--
soda list:rules --help
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$_SERVER['argv'] = ['soda', 'list:rules', '--help'];

require __DIR__ . '/../../soda';
--EXPECTF--
%ADescription:
  List built-in quality rules

Usage:
  list:rules

Options:
%A--help%A
