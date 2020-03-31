<?php

declare(strict_types=1);

$psr11App = require __DIR__ . '/bootstrap.php';

use Symfony\Component\Console\Application;

$cliApp = new Application();

$commands = [
    // TODO add queue listener / sender, MAIL-3
];
foreach ($commands as $command) {
    $cliApp->add($command);
}

$cliApp->run();
