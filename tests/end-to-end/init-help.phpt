--TEST--
soda init --help
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$_SERVER['argv'] = ['soda', 'init', '--help'];

require __DIR__ . '/../../soda';
--EXPECTF--
%ADescription:
  Create soda.php with default quality rules

Usage:
  init

Options:
%A--help%A
