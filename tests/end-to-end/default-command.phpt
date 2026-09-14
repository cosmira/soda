--TEST--
soda runs configured quality checks without a subcommand
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$directory = sys_get_temp_dir() . '/soda-default-' . uniqid();
mkdir($directory);
file_put_contents($directory . '/Example.php', '<?php final class Example {}');
file_put_contents($directory . '/soda.php', <<<'PHP'
<?php
return \Cosmira\Soda\Config\Soda::configure()
    ->withPaths([getcwd()])
    ->with([]);
PHP);
$previous = getcwd();
chdir($directory);
$_SERVER['argv'] = ['soda'];
define('SODA_ENTRY_NO_EXIT', true);
try {
    $status = require __DIR__ . '/../../soda';
    echo 'STATUS=' . $status . PHP_EOL;
} finally {
    chdir($previous);
    unlink($directory . '/Example.php');
    unlink($directory . '/soda.php');
    rmdir($directory);
}
--EXPECTF--
%ASoda Quality
------------------------------------------------------------

[OK] No issues
STATUS=0
