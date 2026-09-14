--TEST--
soda quality --report-json
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

$reportPath = sys_get_temp_dir() . '/soda-quality-' . uniqid() . '.json';
$_SERVER['argv'] = ['soda', 'quality', '--report-json', $reportPath, __DIR__ . '/../quality-fixture'];

define('SODA_ENTRY_NO_EXIT', true);

require __DIR__ . '/../../soda';

$json = file_get_contents($reportPath);
unlink($reportPath);
$data = json_decode($json, true);

if (! is_array($data)) {
    echo "Invalid JSON\n";
    exit(1);
}
if (! isset($data['schema_version'], $data['passed'], $data['violations'])) {
    echo "Missing required keys\n";
    exit(1);
}
if ($data['schema_version'] !== 4) {
    echo "Bad schema_version\n";
    exit(1);
}
if (! is_bool($data['passed'])) {
    echo "Bad passed\n";
    exit(1);
}
if (array_key_exists('metrics', $data)) {
    echo "Unexpected metrics\n";
    exit(1);
}
if (! is_array($data['violations'])) {
    echo "Bad violations\n";
    exit(1);
}
foreach ($data['violations'] as $violation) {
    if (! array_key_exists('recommendation', $violation)) {
        echo "Missing recommendation\n";
        exit(1);
    }
}
foreach (['directories', 'files', 'loc', 'complexity', 'errors'] as $rootMetricKey) {
    if (array_key_exists($rootMetricKey, $data)) {
        echo "Root metric key leaked\n";
        exit(1);
    }
}
echo "OK\n";
--EXPECTF--
%A
OK
