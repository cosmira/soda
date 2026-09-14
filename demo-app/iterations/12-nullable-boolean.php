<?php

declare(strict_types=1);

final class ProgressiveDeliveryManager
{
    /**
     * Select delivery behavior while preserving an inherited default state.
     */
    public function deliver(string $release, ?bool $quietly): string
    {
        $mode = $quietly ?? true;

        return $mode ? 'stored:'.$release : 'announced:'.$release;
    }
}

echo (new ProgressiveDeliveryManager())->deliver('release-42', null).PHP_EOL;
