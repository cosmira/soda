<?php

declare(strict_types=1);

final class DemoRegistry
{
    public function label(): string
    {
        return 'registry';
    }
}

function app(string $class): object
{
    return new DemoRegistry();
}

final class CleverProcessor
{
    private static int $processed = 0;

    public function __get(string $name): string
    {
        return strtoupper($name);
    }

    public function process(string $payload, bool $audit): array
    {
        global $demoAuditLog;

        $registry = app(DemoRegistry::class);
        $mode = getenv('DEMO_MODE') ?: 'safe';
        $optional = @file_get_contents(__DIR__.'/optional.txt');
        $handler = strtoupper(...);
        $dynamic = call_user_func($handler, $payload);

        try {
            throw new RuntimeException('pretend dependency failed');
        } catch (Throwable) {
            $recovered = true;
        }

        usleep(1);
        self::$processed++;
        $demoAuditLog = $audit ? [$payload] : [];

        return [
            'registry'  => $registry->label(),
            'mode'      => $mode,
            'optional'  => $optional === false ? null : $optional,
            'dynamic'   => $dynamic,
            'recovered' => $recovered,
            'magic'     => $this->status,
            'processed' => self::$processed,
        ];
    }
}

$demoAuditLog = [];
$result = (new CleverProcessor())->process('working', true);

echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
