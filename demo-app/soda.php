<?php

declare(strict_types=1);

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\Soda;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with(RuleCatalog::standard());
