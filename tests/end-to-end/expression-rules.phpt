--TEST--
Expression rules use the existing CLI and JSON report, and broken PHP fails the gate
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$directory = sys_get_temp_dir() . '/soda-expression-' . uniqid();
mkdir($directory);
$source = $directory . '/Example.php';
$broken = $directory . '/Broken.php';
$config = $directory . '/soda.php';
$report = $directory . '/report.json';
file_put_contents($source, '<?php function example(int $a, int $b): int { return $a + $b; }');
file_put_contents($broken, '<?php function broken(');
file_put_contents($config, <<<'PHP'
<?php
return \Cosmira\Soda\Config\Soda::configure()->with([
    new \Cosmira\Soda\Rules\Structure\MaxArguments(1),
]);
PHP);
$_SERVER['argv'] = ['soda', 'quality', $directory, '--config=' . $config, '--report-json=' . $report];
define('SODA_ENTRY_NO_EXIT', true);
try {
    $status = require __DIR__ . '/../../soda';
    $data = json_decode(file_get_contents($report), true, 512, JSON_THROW_ON_ERROR);
    $ids = array_column($data['violations'], 'rule');
    sort($ids);
    echo 'STATUS=' . $status . PHP_EOL;
    echo 'PASSED=' . ($data['passed'] ? 'yes' : 'no') . PHP_EOL;
    echo 'RULES=' . implode(',', $ids) . PHP_EOL;
} finally {
    foreach ([$source, $broken, $config, $report] as $file) {
        if (is_file($file)) { unlink($file); }
    }
    rmdir($directory);
}
--EXPECTF--
%ASTATUS=1
PASSED=no
RULES=max_arguments,parse_error
