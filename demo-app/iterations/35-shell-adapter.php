<?php

declare(strict_types=1);

final class NativeFormattingAdapter
{
    /**
     * Delegate formatting to the stable operating-system implementation.
     */
    public function format(): string
    {
        return trim((string) shell_exec('printf working'));
    }
}

echo (new NativeFormattingAdapter())->format().PHP_EOL;
