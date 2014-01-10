<?php

namespace Resque\Test;

use Resque\JobInterface;

class MinimalJob implements JobInterface
{
    public $performed = false;

    public function __construct($queue, array $payload)
    {
    }

    public function perform()
    {
        $this->performed = true;
    }
}
