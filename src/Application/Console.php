<?php

declare(strict_types=1);

namespace Mailer\Application;

use DI\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransport;

class Console extends Application
{
    private RoutableMessageBus $bus;
    private LoggerInterface $logger;
    private RedisTransport $transport;

    private string $receiverName = 'consumer';

    public function __construct(
        RoutableMessageBus $bus,
        LoggerInterface $logger,
        RedisTransport $transport
    ) {
        $this->bus = $bus;
        $this->logger = $logger;
        $this->transport = $transport;

        $this->addCommands([
            $this->getConsumeCommand(),
        ]);

        parent::__construct();
    }

    private function getConsumeCommand()
    {
        $receiversContainer = new Container();
        $receiversContainer->set($this->receiverName, $this->transport);
        return new ConsumeMessagesCommand(
            $this->bus,
            $receiversContainer,
            new EventDispatcher(),
            $this->logger,
            [$this->receiverName],
        );
    }
}
