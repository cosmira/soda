<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Complexity\MaxBooleanConditions;
use Cosmira\Soda\Rules\Complexity\MaxControlNesting;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Complexity\MaxReturnStatements;
use Cosmira\Soda\Rules\Complexity\MaxTryCatchBlocks;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Complexity\NoElseBranches;
use Cosmira\Soda\Rules\Complexity\NoRepeatedCompoundConditions;
use Cosmira\Soda\Rules\Documentation\MultilineConstantPhpDoc;
use Cosmira\Soda\Rules\Documentation\MultilineMethodPhpDoc;
use Cosmira\Soda\Rules\Documentation\MultilinePropertyPhpDoc;
use Cosmira\Soda\Rules\Naming\AvoidRedundantNaming;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix;
use Cosmira\Soda\Rules\Naming\ClassNameLength;
use Cosmira\Soda\Rules\Naming\MaxVariableNameWords;
use Cosmira\Soda\Rules\Naming\MethodNameLength;
use Cosmira\Soda\Rules\Naming\NamespaceNameLength;
use Cosmira\Soda\Rules\Naming\VariableNameLength;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use Cosmira\Soda\Rules\Structure\MaxClassesPerFile;
use Cosmira\Soda\Rules\Structure\MaxClassesPerNamespace;
use Cosmira\Soda\Rules\Structure\MaxClassesPerProject;
use Cosmira\Soda\Rules\Structure\MaxClassLength;
use Cosmira\Soda\Rules\Structure\MaxDependencies;
use Cosmira\Soda\Rules\Structure\MaxEfferentCoupling;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use Cosmira\Soda\Rules\Structure\MaxInterfacesPerClass;
use Cosmira\Soda\Rules\Structure\MaxLayerDominancePercentage;
use Cosmira\Soda\Rules\Structure\MaxLineLength;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
use Cosmira\Soda\Rules\Structure\MaxMethodsPerClass;
use Cosmira\Soda\Rules\Structure\MaxNamespaceDepth;
use Cosmira\Soda\Rules\Structure\MaxPropertiesPerClass;
use Cosmira\Soda\Rules\Structure\MaxPublicMethods;
use Cosmira\Soda\Rules\Structure\MaxTraitsPerClass;
use Cosmira\Soda\Rules\Structure\MethodsFollowCallOrder;
use Cosmira\Soda\Rules\Structure\NoAskThenTellPatterns;
use Cosmira\Soda\Rules\Structure\NoCommentedOutCode;
use Cosmira\Soda\Rules\Structure\NoEmptyCatchBlocks;
use Cosmira\Soda\Rules\Structure\NoTodoFixmeComments;
use Cosmira\Soda\Rules\Structure\NoTrivialDelegatingClasses;
use Cosmira\Soda\Rules\Structure\NoTrivialFactories;
use Cosmira\Soda\Rules\Usage\NoNumericArrayIndex;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use Cosmira\Soda\Rules\Usage\OnlyListArraysAllowed;
use Cosmira\Soda\Rules\Usage\UselessVariableRule;

$properties = new MaxPropertiesPerClass(10);

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
        // Structural
        new MaxFileLoc(2000),
        new MaxLineLength(240),
        new MaxClassesPerFile(1),
        new MaxMethodLength(300),
        new MaxClassLength(2000),
        new MaxArguments(5, $properties),
        new MaxMethodsPerClass(40),
        $properties,
        new MaxPublicMethods(20),
        new MaxDependencies(8),
        new MaxEfferentCoupling(15),
        new MaxTraitsPerClass(10),
        new MaxInterfacesPerClass(5),
        new NoTodoFixmeComments(),
        new NoCommentedOutCode(),
        new OnlyListArraysAllowed(),
        new NoNumericArrayIndex(),
        new NoAssignmentInCondition(),
        new NoComplexControlConditions(),
        new NoElseBranches(),
        new NoUnusedMethods(),
        new NoEmptyCatchBlocks(),
        new NoAskThenTellPatterns(),
        new NoTrivialDelegatingClasses(),
        new NoTrivialFactories(),
        new NoRepeatedCompoundConditions(),
        new MethodsFollowCallOrder(),
        new MultilineMethodPhpDoc(['public', 'protected', 'private']),
        new MultilinePropertyPhpDoc(['public', 'protected', 'private']),
        new MultilineConstantPhpDoc(['public', 'protected', 'private']),
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

        // Naming
        new AvoidRedundantNaming(80),
        new VariableNameLength(min: 1, max: 32),
        new MaxVariableNameWords(maxWords: 3),
        new MethodNameLength(min: 2, max: 64),
        new ClassNameLength(min: 3, max: 64),
        new NamespaceNameLength(min: 3, max: 32),
        new BooleanMethodPrefix(
            ignore: ['runningUnitTests'],
            prefix: ['check'],
        ),

        // Custom
        new UselessVariableRule(),
    ]);
