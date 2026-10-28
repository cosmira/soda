<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php80\Rector\FuncCall\ClassOnObjectRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\MethodCall\PrivatizeLocalGetterToPropertyRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\Closure\ClosureReturnTypeRector;
use Rector\TypeDeclaration\Rector\FuncCall\AddArrayFunctionClosureParamTypeRector;
use RectorLaravel\Rector\ArrayDimFetch\EnvVariableToEnvHelperRector;
use RectorLaravel\Rector\FuncCall\FactoryFuncCallToStaticCallRector;
use RectorLaravel\Rector\FuncCall\RemoveDumpDataDeadCodeRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        'src/',
        'tests/unit/',
        'tests/verification/',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        codingStyle: true,
        instanceOf: true,
    )
    ->withSets([
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
    ])
    ->withComposerBased(phpunit: true)
    ->withRules([
        RemoveDumpDataDeadCodeRector::class,
        FactoryFuncCallToStaticCallRector::class,
    ])
    ->withSkip([
        AddArrowFunctionReturnTypeRector::class,
        PrivatizeLocalGetterToPropertyRector::class,
        ClosureReturnTypeRector::class,
        ClosureToArrowFunctionRector::class,
        EnvVariableToEnvHelperRector::class,
        // SodaInitFileEmitter uses string FQCNs intentionally (::class would add Ce=33)
        // UselessVariableAnalyzer uses strings intentionally (::class would add Ce)
        StringClassNameToClassConstantRector::class => [
            'src/Config/SodaInitFileEmitter.php',
            'src/Rules/Usage/UselessVariableAnalyzer.php',
        ],
        ClassOnObjectRector::class => [
            'src/Rules/Usage/UselessVariableAnalyzer.php',
        ],
        AddArrayFunctionClosureParamTypeRector::class => [
            'src/Rules/Usage/UselessVariableAnalyzer.php',
        ],
    ])
    ->withMemoryLimit('3G')
    ->withPhpSets(php82: true);
