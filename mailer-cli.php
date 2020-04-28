<?php

declare(strict_types=1);

$psr11App = require __DIR__ . '/bootstrap.php';

use Mailer\Application\Console;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\TransportInterface;

$cliApp = new Console(
    $psr11App->get(RoutableMessageBus::class),
    $psr11App->get(LoggerInterface::class),
    $psr11App->get(TransportInterface::class),
);

$cliApp->run();
