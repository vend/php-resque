<?php

namespace Resque\Console;

use Doxport\Action\ArchiveDelete;
use Doxport\ConstraintPass;
use Doxport\JoinPass;
use Doxport\Schema;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueuesCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('queues')
            ->setDescription('Outputs information about queues');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
    }
}
