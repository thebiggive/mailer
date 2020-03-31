<?php

declare(strict_types=1);

// Instantiate PHP-DI ContainerBuilder
use DI\ContainerBuilder;

require __DIR__ . '/vendor/autoload.php';

$containerBuilder = new ContainerBuilder();

if (getenv('APP_ENV') !== 'local') { // Compile cache on staging & production
    $containerBuilder->enableCompilation(__DIR__ . '/var/cache');
}

// Set up settings
$settings = require __DIR__ . '/app/settings.php';
$settings($containerBuilder);

// Set up dependencies
$dependencies = require __DIR__ . '/app/dependencies.php';
$dependencies($containerBuilder);

// Build PHP-DI Container instance
return $containerBuilder->build();
