<?php

declare(strict_types=1);

use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\Complexity\MaxCyclomaticComplexity;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxArguments;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxClassLength;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxFileLoc;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodLength;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodsPerClass;

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
        new MaxMethodLength(30),
        new MaxClassLength(600),
        new MaxArguments(4),
        new MaxMethodsPerClass(25),
        new MaxFileLoc(500),
        new MaxCyclomaticComplexity(12),
    ]);
