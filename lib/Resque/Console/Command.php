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
        $resque = new Resque($this->getRedis());

        if (($helper = $this->getHelper('logger'))) {
            /* @var LoggerHelper $helper */
            $resque->setLogger($helper->getLogger());
        } else {
            $resque->setLogger(new ConsoleLogger($output));
        }

        return $resque;
    }
}
