<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use RectorLaravel\Rector\ArrayDimFetch\EnvVariableToEnvHelperRector;
use RectorLaravel\Rector\ArrayDimFetch\RequestVariablesToRequestFacadeRector;
use RectorLaravel\Rector\ArrayDimFetch\ServerVariableToRequestFacadeRector;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Set\LaravelSetProvider;

/*
 * Protect framework/runtime integration files from automated rewrites.
 *
 * These paths contain dynamic class-name building, namespace string composition,
 * container bootstrapping, module runtime loading, autoloader behavior, and other
 * framework glue. Rector is useful for ordinary application code, but these files
 * are sensitive to seemingly-correct transformations such as converting runtime
 * class strings into ::class references or removing defensive casts around config
 * and manifest values.
 *
 * We intentionally keep these files under manual review so automated refactors do
 * not silently change bootstrap semantics or module discovery behavior.
 */
$protectedInfrastructurePaths = [
    __DIR__.'/app/Http/Middleware/*',
    __DIR__.'/app/Modules/*',
    __DIR__.'/app/Providers/*',
    __DIR__.'/bootstrap/app.php',
    __DIR__.'/database/seeders/DatabaseSeeder.php',
    __DIR__.'/routes/console.php',
];

return RectorConfig::configure()
    ->withSetProviders(LaravelSetProvider::class)
    ->withSets([
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_FACTORIES,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
    ])
    ->withImportNames(
        removeUnusedImports: true,
    )
    ->withComposerBased(laravel: true)
    ->withCache(
        cacheDirectory: '/tmp/rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/modules',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        MakeInheritedMethodVisibilitySameAsParentRector::class,
        EnvVariableToEnvHelperRector::class,
        RequestVariablesToRequestFacadeRector::class,
        ServerVariableToRequestFacadeRector::class,
        ...$protectedInfrastructurePaths,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        codingStyle: true,
    )
    ->withPhpSets();
