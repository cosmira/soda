<?php

declare(strict_types=1);

use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\{Breathing\MinCodeBreathingScore,
    Breathing\MinCodeOxygenLevel,
    Breathing\MinIdentifierReadabilityScore,
    Breathing\MinVisualBreathingIndex,
    Complexity\MaxBooleanConditions,
    Complexity\MaxControlNesting,
    Complexity\MaxCyclomaticComplexity,
    Complexity\MaxLogicalComplexityFactor,
    Complexity\MaxReturnStatements,
    Complexity\MaxTryCatchBlocks,
    Complexity\MaxWeightedCognitiveDensity,
    ListOnlyArray\OnlyListArraysAllowed,
    Naming\AvoidRedundantNaming,
    Naming\BooleanMethodPrefix,
    Naming\ClassNameLength,
    Naming\MethodNameLength,
    Naming\VariableNameLength,
    NoUnusedMethods,
    NumericArrayIndex\NoNumericArrayIndex,
    Structural\MaxArguments,
    Structural\MaxClassesPerFile,
    Structural\MaxClassesPerNamespace,
    Structural\MaxClassesPerProject,
    Structural\MaxClassLength,
    Structural\MaxDependencies,
    Structural\MaxEfferentCoupling,
    Structural\MaxFileLoc,
    Structural\MaxInterfacesPerClass,
    Structural\MaxLayerDominancePercentage,
    Structural\MaxLineLength,
    Structural\MaxMethodLength,
    Structural\MaxMethodsPerClass,
    Structural\MaxNamespaceDepth,
    Structural\MaxPropertiesPerClass,
    Structural\MaxPublicMethods,
    Structural\MaxTraitsPerClass,
    Structural\MethodsFollowCallOrder,
    Structural\MultilineMethodPhpDoc,
    Structural\MultilinePropertyPhpDoc,
    Structural\NoAskThenTellPatterns,
    Structural\NoCommentedOutCode,
    Structural\NoEmptyCatchBlocks,
    Structural\NoTodoFixmeComments,
    UselessVariableRule};

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
        // Structural
        new MaxFileLoc(1200),
        new MaxLineLength(240),
        new MaxClassesPerFile(1),
        new MaxMethodLength(100),
        new MaxClassLength(1000),
        new MaxArguments(5),
        new MaxMethodsPerClass(40),
        new MaxPropertiesPerClass(10),
        new MaxPublicMethods(20),
        new MaxDependencies(8),
        new MaxEfferentCoupling(15),
        new MaxTraitsPerClass(10),
        new MaxInterfacesPerClass(5),
        new NoTodoFixmeComments(),
        new NoCommentedOutCode(),
        new OnlyListArraysAllowed(),
        new NoNumericArrayIndex(),
        new NoUnusedMethods(),
        new NoEmptyCatchBlocks(),
        new NoAskThenTellPatterns(),
        new MethodsFollowCallOrder(40),
        new MultilineMethodPhpDoc(['public'], maxViolations: 379),
        new MultilinePropertyPhpDoc(['public'], maxViolations: 7),
        new MaxLayerDominancePercentage(50, 4),
        new MaxNamespaceDepth(5),
        new MaxClassesPerNamespace(40),
        new MaxClassesPerProject(300),

        // Complexity
        new MaxCyclomaticComplexity(10),
        new MaxControlNesting(3),
        new MaxReturnStatements(4),
        new MaxBooleanConditions(4),
        new MaxTryCatchBlocks(2),
        new MaxWeightedCognitiveDensity(70),
        new MaxLogicalComplexityFactor(62),

        // Breathing
        new MinCodeBreathingScore(100),
        new MinVisualBreathingIndex(70),
        new MinIdentifierReadabilityScore(90),
        new MinCodeOxygenLevel(100),

        // Naming
        new AvoidRedundantNaming(80),
        new VariableNameLength(min: 1, max: 32),
        new MethodNameLength(min: 2, max: 64),
        new ClassNameLength(min: 3, max: 64),
        new BooleanMethodPrefix(
            ignore: ['runningUnitTests'],
            prefix: ['check'],
        ),

        // Custom
        new UselessVariableRule(),
    ]);
