<?php

declare(strict_types=1);

namespace Mailer\Application\Commands;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends SymfonyCommand
{
    abstract protected function doExecute(InputInterface $input, OutputInterface $output): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->start($output);
        $return = $this->doExecute($input, $output);
        $this->finish($output);

        return $return;
    }

    protected function start(OutputInterface $output): void
    {
        $output->writeln($this->getName() . ' starting!');
    }

    protected function finish(OutputInterface $output): void
    {
        $output->writeln($this->getName() . ' complete!');
    }
}
