--TEST--
soda shorthand quality command passes for its own source
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$_SERVER['argv'] = [
    'soda',
    'q',
    __DIR__ . '/../../src',
    '--config=' . __DIR__ . '/../../soda.php',
];

define('SODA_ENTRY_NO_EXIT', true);
$status = require __DIR__ . '/../../soda';

echo 'STATUS=' . $status . PHP_EOL;
--EXPECTF--
%ASoda Quality
------------------------------------------------------------

[OK] No issues
STATUS=0
