<?php

declare(strict_types=1);

use Cosmira\Soda\Analyzer;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\ModuleBoundaries;
use Cosmira\Soda\Rules\Architecture\NoDependencyCycles;
use Cosmira\Soda\Rules\Complexity\MaxBooleanConditions;
use Cosmira\Soda\Rules\Complexity\MaxCognitiveComplexity;
use Cosmira\Soda\Rules\Complexity\MaxConsecutiveLocalAssignments;
use Cosmira\Soda\Rules\Complexity\MaxControlNesting;
use Cosmira\Soda\Rules\Complexity\MaxCyclomaticComplexity;
use Cosmira\Soda\Rules\Complexity\MaxStatementsPerCallable;
use Cosmira\Soda\Rules\Complexity\MaxTryCatchBlocks;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInReturn;
use Cosmira\Soda\Rules\Complexity\NoChainedAssignments;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Complexity\NoElseBranches;
use Cosmira\Soda\Rules\Complexity\NoEmptyFinallyBlocks;
use Cosmira\Soda\Rules\Complexity\NoIdenticalBranches;
use Cosmira\Soda\Rules\Complexity\NoNestedTernary;
use Cosmira\Soda\Rules\Complexity\NoRedundantBooleanComparisons;
use Cosmira\Soda\Rules\Complexity\NoRedundantBooleanReturns;
use Cosmira\Soda\Rules\Complexity\NoRepeatedCompoundConditions;
use Cosmira\Soda\Rules\Complexity\NoRepeatedStringLiterals;
use Cosmira\Soda\Rules\Complexity\NoReturnInFinally;
use Cosmira\Soda\Rules\Complexity\NoUnneededTernary;
use Cosmira\Soda\Rules\Complexity\NoUselessConcat;
use Cosmira\Soda\Rules\Complexity\NoUselessReturn;
use Cosmira\Soda\Rules\Complexity\PreferCombinedConditions;
use Cosmira\Soda\Rules\Design\FragmentedOwnerBehavior;
use Cosmira\Soda\Rules\Design\RepeatedConfigurationSequence;
use Cosmira\Soda\Rules\Design\RepeatedForeignStateDecision;
use Cosmira\Soda\Rules\Design\RepeatedForeignStateUpdate;
use Cosmira\Soda\Rules\Design\RepeatedOwnerConcern;
use Cosmira\Soda\Rules\Design\RepresentationChurn;
use Cosmira\Soda\Rules\Design\ScatteredOwnerConcern;
use Cosmira\Soda\Rules\Documentation\MultilineConstantPhpDoc;
use Cosmira\Soda\Rules\Documentation\MultilineMethodPhpDoc;
use Cosmira\Soda\Rules\Documentation\MultilinePropertyPhpDoc;
use Cosmira\Soda\Rules\Documentation\NoVacuousDocblocks;
use Cosmira\Soda\Rules\Naming\AvoidRedundantNaming;
use Cosmira\Soda\Rules\Naming\BooleanMethodPrefix;
use Cosmira\Soda\Rules\Naming\ClassNameLength;
use Cosmira\Soda\Rules\Naming\MaxVariableNameWords;
use Cosmira\Soda\Rules\Naming\MethodNameLength;
use Cosmira\Soda\Rules\Naming\NamespaceNameLength;
use Cosmira\Soda\Rules\Naming\NoDumpsterSuffixes;
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
use Cosmira\Soda\Rules\Usage\NoReassignedParameters;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use Cosmira\Soda\Rules\Usage\OnlyListArraysAllowed;
use Cosmira\Soda\Rules\Usage\UselessVariableRule;

$properties = new MaxPropertiesPerClass(10);

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
        new RepresentationChurn(),
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
        new NoIdenticalBranches(),
        new NoNestedTernary(),
        new NoUnusedMethods(),
        new NoEmptyCatchBlocks(),
        new ModuleBoundaries('Cosmira\\Soda\\Rules', exports: []),
        new NoDependencyCycles([
            'Cosmira\Soda\Analysis',
            'Cosmira\Soda\Commands',
            'Cosmira\Soda\Config',
            'Cosmira\Soda\Reporting',
            'Cosmira\Soda\Rules',
            'Cosmira\Soda\Support',
        ]),
        new NoReassignedParameters(),
        new NoAskThenTellPatterns(),
        new NoTrivialDelegatingClasses(),
        new NoTrivialFactories(contracts: [
            // Published entry points retained for callers of the existing fluent API.
            Analyzer::class.'::paths',
            Soda::class.'::configure',
        ]),
        new NoRepeatedCompoundConditions(),
        new MethodsFollowCallOrder(),
        new MultilineMethodPhpDoc(['public', 'protected', 'private']),
        new MultilinePropertyPhpDoc(['public', 'protected', 'private']),
        new MultilineConstantPhpDoc(['public', 'protected', 'private']),
        new NoVacuousDocblocks(),
        new NoDumpsterSuffixes(),
        new MaxLayerDominancePercentage(50, 4),
        new MaxNamespaceDepth(5),
        new MaxClassesPerNamespace(40),
        new MaxClassesPerProject(293),

        // Owner behavior
        new FragmentedOwnerBehavior(),
        new RepeatedOwnerConcern(),
        new ScatteredOwnerConcern(),
        new RepeatedConfigurationSequence(),
        new RepeatedForeignStateDecision(),
        new RepeatedForeignStateUpdate(),

        // Complexity
        new MaxCognitiveComplexity(15),
        new MaxConsecutiveLocalAssignments(8),
        new MaxStatementsPerCallable(20),
        new NoAssignmentInReturn(),
        new NoChainedAssignments(),
        new NoEmptyFinallyBlocks(),
        new NoRepeatedStringLiterals(3),
        new NoReturnInFinally(),
        new NoUnneededTernary(),
        new NoRedundantBooleanReturns(),
        new NoRedundantBooleanComparisons(),
        new PreferCombinedConditions(),
        new NoUselessConcat(),
        new NoUselessReturn(),
        new MaxCyclomaticComplexity(10),
        new MaxControlNesting(3),
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
