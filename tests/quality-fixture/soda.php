<?php

declare(strict_types=1);

use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\Complexity\MaxCyclomaticComplexity;
use Bunnivo\Soda\Plugins\Rules\Naming\ClassNameLength;
use Bunnivo\Soda\Plugins\Rules\Naming\MethodNameLength;
use Bunnivo\Soda\Plugins\Rules\Naming\VariableNameLength;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxArguments;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxClassLength;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxFileLoc;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxLineLength;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodLength;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxMethodsPerClass;
use Bunnivo\Soda\Plugins\Rules\Structural\MethodsFollowCallOrder;

return Soda::configure()
    ->withPaths([
        __DIR__,
    ])
    ->with([
        new MaxMethodLength(100),
        new MaxClassLength(500),
        new MaxArguments(16),
        new MaxMethodsPerClass(21),
        new MaxFileLoc(700),
        new MaxLineLength(100),
        new MethodsFollowCallOrder(),
        new MaxCyclomaticComplexity(26),
        new VariableNameLength(min: 3, max: 16),
        new MethodNameLength(min: 3, max: 32),
        new ClassNameLength(min: 3, max: 32),
    ]);
