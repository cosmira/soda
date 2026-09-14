<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Complexity\NoElseBranches;

return Soda::configure()
    ->withPaths([__DIR__])
    ->with([
        new NoAssignmentInCondition(),
        new NoElseBranches(),
        new NoComplexControlConditions(),
    ]);
