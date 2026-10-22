<?php
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/phpmailer',
    ])
    ->withSets([LevelSetList::UP_TO_PHP_83]);