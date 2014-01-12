<?php

namespace Resque\Test;

use Resque\AbstractJob;

class Job extends AbstractJob
{
    public $performed = false;

    public function perform()
    {
        $this->performed = true;
    }

    /**
     * @return int See Resque\Job\Status::STATUS_*
     */
    public function getStatusCode()
    {
        return $this->getStatus()->get();
    }

    /**
     * @return string ID of the recreated job
     */
    public function recreate()
    {
        parent::recreate();
    }

    /**
     * @return \Resque\Job\Status
     */
    public function getStatus()
    {
        return parent::getStatus();
    }
}
