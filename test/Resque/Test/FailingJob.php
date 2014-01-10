<?php

namespace Resque\Test;

use Resque\AbstractJob;

class FailingJob extends AbstractJob
{
    /**
     * Execute the job
     *
     * @throws \Exception
     * @return bool
     */
    public function perform()
    {
        throw new \Exception('This job just failed');
    }
}
