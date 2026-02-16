<?php

declare(strict_types=1);

namespace Mailer\Application;

use DI\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class Console extends Application
{
    private string $receiverName = 'consumer';

    public function __construct(
        private RoutableMessageBus $bus,
        private LoggerInterface $logger,
        private TransportInterface $transport
    ) {
        $this->addCommands([
            $this->getConsumeCommand(),
        ]);

        parent::__construct();
    }

    private function getConsumeCommand(): ConsumeMessagesCommand
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
