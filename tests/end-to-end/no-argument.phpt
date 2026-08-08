--TEST--
soda (no args - show commands)
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$_SERVER['argv'] = ['soda'];

require __DIR__ . '/../../soda';
--EXPECTF--
Soda %s

Usage:
  command [options] [arguments]

Options:
%aAvailable commands:
  analyse     Analyse PHP project size and collect metrics
  completion  Dump the shell completion script
  help        Display help for a command
  init        Create soda.php with default quality rules
  list        List commands
  quality     Analyse code quality and check against configured thresholds
 list
  list:rules  List built-in quality rules
