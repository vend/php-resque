<?php

namespace Doxport\Console;

use Psr\Log\LoggerInterface;
use Resque\Resque;
use Symfony\Component\Console\Command\Command as CommandComponent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends CommandComponent
{
    /**
     * @return Resque
     */
    public function getResque()
    {
        return $this->getHelper('redis');
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->getHelper('logger');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Nada.
    }
}
