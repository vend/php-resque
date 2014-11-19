<?php

namespace Resque\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnqueueCommand extends Command
{
    /**
     * @return void
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('enqueue')
            ->setDescription('Enqueues a job into a queue')
            ->addArgument(
                'queue',
                InputArgument::REQUIRED,
                'The name of the queue where the job should be enqueued'
            )
            ->addArgument(
                'class',
                InputArgument::REQUIRED,
                'The class name of the job to enqueue'
            )
            ->addOption(
                'payload',
                'p',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'A payload to send with the job'
            )
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_REQUIRED,
                'A JSON payload to send with the job (specify as a JSON encoded string)'
            )
            ->addOption(
                'track',
                't',
                InputOption::VALUE_NONE,
                'If present, the job will be tracked'
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

        $id = $resque->enqueue(
            $queue,
            $input->getArgument('class'),
            $this->getPayload($input),
            $input->getOption('track')
        );

        if ($id) {
            $message = sprintf('Enqueued job as %s to %s', $id, $queue);
        } else {
            $message = sprintf('Enqueued job to %s', $queue);
        }

        $output->writeln($message);
        return 0;
    }

    /**
     * @param InputInterface $input
     * @return array|mixed
     */
    protected function getPayload(InputInterface $input)
    {
        if ($input->hasOption('payload')) {
            return $input->getOption('payload');
        }

        if ($input->hasOption('json')) {
            return json_decode($input->getOption('json'));
        }

        return [];
    }
}
