<?php

namespace Resque\Console;

use Resque\Worker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('worker')
            ->setDescription('Runs a Resque worker')
            ->addOption(
                'queue',
                'q',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'A queue to listen to and run jobs from'
            )
            ->addOption();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resque = $this->getResque();

        $queues = $input->getOption('queue');

        $worker = new Worker($resque, $queues);
        $worker->work($input->getOption('interval'));
    }
}
