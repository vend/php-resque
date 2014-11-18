<?php

namespace Resque\Console;

use Resque\Client\ClientInterface;
use Resque\Resque;
use Symfony\Component\Console\Command\Command as CommandComponent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @return \Resque\Resque
     */
    public function getResque()
    {
        return new Resque($this->getRedis());
    }
}
