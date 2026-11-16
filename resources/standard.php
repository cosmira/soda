<?php

declare(strict_types=1);

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoAnonymousClasses;
use Cosmira\Soda\Rules\Architecture\NoBehaviorMagicMethods;
use Cosmira\Soda\Rules\Architecture\NoBlockingSleep;
use Cosmira\Soda\Rules\Architecture\NoCatchAllExceptions;
use Cosmira\Soda\Rules\Architecture\NoClosureRebinding;
use Cosmira\Soda\Rules\Architecture\NoDependencyCycles;
use Cosmira\Soda\Rules\Architecture\NoDirectFilesystemMutation;
use Cosmira\Soda\Rules\Architecture\NoDirectResponseGlobals;
use Cosmira\Soda\Rules\Architecture\NoDynamicInvocation;
use Cosmira\Soda\Rules\Architecture\NoDynamicProperties;
use Cosmira\Soda\Rules\Architecture\NoEmptyLocalInterfaces;
use Cosmira\Soda\Rules\Architecture\NoEnvironmentReads;
use Cosmira\Soda\Rules\Architecture\NoErrorSuppression;
use Cosmira\Soda\Rules\Architecture\NoGlobalState;
use Cosmira\Soda\Rules\Architecture\NoImplicitArguments;
use Cosmira\Soda\Rules\Architecture\NoModeParameters;
use Cosmira\Soda\Rules\Architecture\NoNativeDeserialization;
use Cosmira\Soda\Rules\Architecture\NoProcessConfigurationMutation;
use Cosmira\Soda\Rules\Architecture\NoRedundantLocalInterfaces;
use Cosmira\Soda\Rules\Architecture\NoReflection;
use Cosmira\Soda\Rules\Architecture\NoRepeatedTypeDispatch;
use Cosmira\Soda\Rules\Architecture\NoRuntimeCodeEvaluation;
use Cosmira\Soda\Rules\Architecture\NoRuntimeHooks;
use Cosmira\Soda\Rules\Architecture\NoRuntimeIncludes;
use Cosmira\Soda\Rules\Architecture\NoRuntimeTypeAliases;
use Cosmira\Soda\Rules\Architecture\NoSingletonAccess;
use Cosmira\Soda\Rules\Architecture\NoSubprocessExecution;
use Cosmira\Soda\Rules\Architecture\NoSymbolTableMutation;
use Cosmira\Soda\Rules\Architecture\NoUntypedParameters;
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
use Cosmira\Soda\Rules\Complexity\NoDeclarationsBeforeEarlyExit;
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
use Cosmira\Soda\Rules\Complexity\PreferCombinedGuards;
use Cosmira\Soda\Rules\Complexity\PreferContinue;
use Cosmira\Soda\Rules\Complexity\PreferNullCoalesceAssignment;
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
use Cosmira\Soda\Rules\Documentation\NoConsecutiveBlockComments;
use Cosmira\Soda\Rules\Naming\AvoidRedundantNaming;
use Cosmira\Soda\Rules\Naming\ClassNameLength;
use Cosmira\Soda\Rules\Naming\MethodNameLength;
use Cosmira\Soda\Rules\Naming\NamespaceNameLength;
use Cosmira\Soda\Rules\Naming\VariableNameLength;
use Cosmira\Soda\Rules\Structure\MaxClassesPerFile;
use Cosmira\Soda\Rules\Structure\MaxClassesPerNamespace;
use Cosmira\Soda\Rules\Structure\MaxClassesPerProject;
use Cosmira\Soda\Rules\Structure\MaxClassLength;
use Cosmira\Soda\Rules\Structure\MaxDelegationDepth;
use Cosmira\Soda\Rules\Structure\MaxDependencies;
use Cosmira\Soda\Rules\Structure\MaxEffectiveClassLength;
use Cosmira\Soda\Rules\Structure\MaxEffectiveMethodsPerClass;
use Cosmira\Soda\Rules\Structure\MaxEfferentCoupling;
use Cosmira\Soda\Rules\Structure\MaxFileLoc;
use Cosmira\Soda\Rules\Structure\MaxInterfacesPerClass;
use Cosmira\Soda\Rules\Structure\MaxLayerDominancePercentage;
use Cosmira\Soda\Rules\Structure\MaxLineLength;
use Cosmira\Soda\Rules\Structure\MaxMethodLength;
use Cosmira\Soda\Rules\Structure\MaxMethodsPerClass;
use Cosmira\Soda\Rules\Structure\MaxNamespaceDepth;
use Cosmira\Soda\Rules\Structure\MaxPublicMethods;
use Cosmira\Soda\Rules\Structure\MaxTraitsPerClass;
use Cosmira\Soda\Rules\Structure\MethodsFollowCallOrder;
use Cosmira\Soda\Rules\Structure\NoAskThenTellPatterns;
use Cosmira\Soda\Rules\Structure\NoCommentedOutCode;
use Cosmira\Soda\Rules\Structure\NoEmptyCatchBlocks;
use Cosmira\Soda\Rules\Structure\NoTodoFixmeComments;
use Cosmira\Soda\Rules\Structure\NoTrivialDelegatingClasses;
use Cosmira\Soda\Rules\Structure\NoTrivialFactories;
use Cosmira\Soda\Rules\Structure\PreferEnumForPublicConstants;
use Cosmira\Soda\Rules\Usage\NoNullableCollections;
use Cosmira\Soda\Rules\Usage\NoNumericArrayIndex;
use Cosmira\Soda\Rules\Usage\NoReassignedParameters;
use Cosmira\Soda\Rules\Usage\NoRedundantArrayKeys;
use Cosmira\Soda\Rules\Usage\NoTemporaryReturnVariable;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use Cosmira\Soda\Rules\Usage\NoUnusedParameters;
use Cosmira\Soda\Rules\Usage\NoUnusedPrivateState;
use Cosmira\Soda\Rules\Usage\OnlyListArraysAllowed;
use Cosmira\Soda\Rules\Usage\PreferIncludes;
use Cosmira\Soda\Rules\Usage\PreferStringStartsEndsWith;
use Cosmira\Soda\Rules\Usage\UselessVariableRule;

return Soda::configure()
    ->withPaths(['src/'])
    ->with([
        new RepresentationChurn(),
        new MultilineMethodPhpDoc(['public', 'protected', 'private']),
        new MultilinePropertyPhpDoc(['public', 'protected', 'private']),
        new MultilineConstantPhpDoc(['public', 'protected', 'private']),
        new MaxDelegationDepth(3),
        new MaxEffectiveMethodsPerClass(40),
        new MaxEffectiveClassLength(500),
        new NoModeParameters(),
        new NoRepeatedTypeDispatch(minMethods: 2),
        new NoUntypedParameters(),
        new NoUnusedPrivateState(),
        new NoDependencyCycles(),
        new NoEmptyLocalInterfaces(),
        new NoRedundantLocalInterfaces(),
        new NoUnusedParameters(),
        new MaxMethodLength(100),
        new MaxClassLength(500),
        new MaxMethodsPerClass(40),
        new MaxFileLoc(700),
        new MaxLineLength(100),
        new PreferEnumForPublicConstants(),
        new MaxPublicMethods(20),
        new MaxDependencies(8),
        new NoTodoFixmeComments(),
        new NoCommentedOutCode(),
        new NoEmptyCatchBlocks(),
        new NoAskThenTellPatterns(),
        new NoTrivialDelegatingClasses(),
        new MethodsFollowCallOrder(),
        new OnlyListArraysAllowed(),
        new NoNumericArrayIndex(),
        new NoRedundantArrayKeys(),
        new NoNullableCollections(),
        new NoUnusedMethods(),
        new NoTemporaryReturnVariable(),
        new UselessVariableRule(),
        new NoAssignmentInCondition(),
        new NoElseBranches(),
        new NoComplexControlConditions(),
        new NoSingletonAccess(),
        new NoReflection(),
        new NoRuntimeHooks(),
        new NoRuntimeCodeEvaluation(),
        new NoProcessConfigurationMutation(),
        new NoDirectResponseGlobals(),
        new NoSymbolTableMutation(),
        new NoImplicitArguments(),
        new NoDirectFilesystemMutation(),
        new NoSubprocessExecution(),
        new NoNativeDeserialization(),
        new NoRuntimeIncludes(),
        new NoRuntimeTypeAliases(),
        new NoDynamicProperties(),
        new NoAnonymousClasses(),
        new NoClosureRebinding(),
        new NoGlobalState(),
        new NoEnvironmentReads(),
        new NoErrorSuppression(),
        new NoDynamicInvocation(),
        new NoCatchAllExceptions(),
        new NoBehaviorMagicMethods(),
        new NoBlockingSleep(),
        new MaxEfferentCoupling(15),
        new MaxClassesPerFile(1),
        new MaxNamespaceDepth(4),
        new MaxClassesPerNamespace(15),
        new MaxLayerDominancePercentage(50, 4),
        new MaxTraitsPerClass(10),
        new MaxInterfacesPerClass(5),
        new MaxClassesPerProject(300),
        new MaxCyclomaticComplexity(8),
        new MaxControlNesting(3),
        new MaxStatementsPerCallable(20),
        new MaxBooleanConditions(4),
        new MaxTryCatchBlocks(2),
        new AvoidRedundantNaming(80),
        new VariableNameLength(min: 2, max: 16),
        new MethodNameLength(min: 1, max: 32),
        new ClassNameLength(min: 3, max: 32),
        new NamespaceNameLength(min: 3, max: 32),
        new NoEmptyFinallyBlocks(),
        new MaxCognitiveComplexity(15),
        new NoTrivialFactories(),
        new NoRepeatedCompoundConditions(minMethods: 3),
        new NoConsecutiveBlockComments(),
        new NoIdenticalBranches(),
        new NoNestedTernary(),
        new NoAssignmentInReturn(),
        new NoChainedAssignments(),
        new NoUnneededTernary(),
        new NoRedundantBooleanComparisons(),
        new NoRedundantBooleanReturns(),
        new NoUselessReturn(),
        new NoUselessConcat(),
        new NoReturnInFinally(),
        new NoReassignedParameters(),
        new MaxConsecutiveLocalAssignments(8),
        new NoRepeatedStringLiterals(3),
        new FragmentedOwnerBehavior(minClasses: 3),
        new RepeatedOwnerConcern(minMethods: 3, minOwners: 2),
        new ScatteredOwnerConcern(minMethods: 3),
        new RepeatedConfigurationSequence(minOccurrences: 3, minConfigurationCalls: 2),
        new RepeatedForeignStateDecision(minConsumers: 3),
        new RepeatedForeignStateUpdate(minConsumers: 3),
        new NoDeclarationsBeforeEarlyExit(),
        new PreferCombinedConditions(),
        new PreferCombinedGuards(),
        new PreferContinue(),
        new PreferIncludes(),
        new PreferStringStartsEndsWith(),
        new PreferNullCoalesceAssignment(),
    ]);
