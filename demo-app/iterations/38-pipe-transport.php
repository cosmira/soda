<?php

declare(strict_types=1);

final class LightweightPipeTransport
{
    /**
     * Read a native producer through a deliberately minimal stream contract.
     */
    public function receive(): string
    {
        $pipe = popen('printf working', 'r');
        $payload = stream_get_contents($pipe);
        pclose($pipe);

        return $payload;
    }
}

echo (new LightweightPipeTransport())->receive().PHP_EOL;
