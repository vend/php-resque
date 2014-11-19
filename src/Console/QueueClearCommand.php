<?php

namespace Resque\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueueClearCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('queue:clear')
            ->setDescription('Clears a specified queue')
            ->addArgument(
                'queue',
                InputArgument::REQUIRED,
                'The name of the queue to clear'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resque = $this->getResque($output);
        $queue = $input->getArgument('queue');

        $cleared = $resque->size($queue);
        $resque->clearQueue($queue);

        $output->writeln('Cleared ' . $cleared . ' jobs on queue ' . $queue);
        return 0;
    }
}
