<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Naming\ClassNameLength;
use Cosmira\Soda\Rules\Naming\MethodNameLength;
use Cosmira\Soda\Rules\Naming\VariableNameLength;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use Cosmira\Soda\Rules\Structure\MaxClassLength;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use Cosmira\Soda\Rules\Structure\MaxLineLength;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
use Cosmira\Soda\Rules\Structure\MaxMethodsPerClass;
use Cosmira\Soda\Rules\Structure\MethodsFollowCallOrder;

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
