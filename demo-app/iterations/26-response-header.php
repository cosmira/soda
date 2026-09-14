<?php

declare(strict_types=1);

final class CacheAwareResponder
{
    /**
     * Render a response together with its framework-independent cache policy.
     */
    public function render(string $payload): string
    {
        header('Cache-Control: private');

        return $payload;
    }
}

echo (new CacheAwareResponder())->render('dashboard').PHP_EOL;
