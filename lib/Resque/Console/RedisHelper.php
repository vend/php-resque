<?php

namespace Resque\Console;

use Symfony\Component\Console\Helper\Helper;

class RedisHelper extends Helper
{
    protected $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getName()
    {
        return 'redis';
    }
}
