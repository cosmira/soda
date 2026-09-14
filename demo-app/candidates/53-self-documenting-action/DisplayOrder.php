<?php

declare(strict_types=1);

final class DisplayOrder
{
    public function __invoke(string $number): string
    {
        return 'order:'.$number;
    }
}

echo (new DisplayOrder)->__invoke('ORD-2048').PHP_EOL;
