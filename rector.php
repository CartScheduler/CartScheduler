<?php

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Set\ValueObject\LevelSetList;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
//    ->withImportNames()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkipPath(__DIR__.'/bootstrap/cache')
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        LaravelLevelSetList::UP_TO_LARAVEL_110,
    ])
    ->withPreparedSets(
        //        deadCode: true,
        //        codeQuality: true
    )
    ->withSkip([
        ClosureToArrowFunctionRector::class => [
            __DIR__.'/src/app/Providers/AuthServiceProvider.php',
        ],
    ]);
