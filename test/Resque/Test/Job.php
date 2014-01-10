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
}
