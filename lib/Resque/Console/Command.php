<?php

namespace Resque\Console;

use Resque\Client\ClientInterface;
use Resque\Resque;
use Symfony\Component\Console\Command\Command as CommandComponent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

abstract class Command extends CommandComponent
{
    /**
     * @return ClientInterface
     */
    public function getRedis()
    {
        return $this->getHelper('redis')->getClient();
    }

    /**
     * @param OutputInterface $output
     * @return \Resque\Resque
     */
    public function getResque(OutputInterface $output)
    {
        if ($this->getHelper('logger')) {

        }


        $logger = new ConsoleLogger($output);

        $resque = new Resque($this->getRedis());
        $resque->setLogger($logger);

        return $resque;
    }
}
