<?php

declare(strict_types=1);

final class LegacyEnvelopeCodec
{
    /**
     * Decode the trusted native envelope used by the internal message broker.
     */
    public function decode(): string
    {
        return unserialize('s:7:"working";');
    }
}

echo (new LegacyEnvelopeCodec())->decode().PHP_EOL;
