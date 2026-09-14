<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use Cosmira\Soda\Rules\Structure\MaxClassLength;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
use Cosmira\Soda\Rules\Structure\MaxMethodsPerClass;

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
