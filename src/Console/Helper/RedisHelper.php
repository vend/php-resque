<?php

namespace Resque\Console\Helper;

use Resque\Client\ClientInterface;
use Symfony\Component\Console\Helper\Helper;

/**
 * Redis CLI helper
 */
class RedisHelper extends Helper
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @param ClientInterface $client
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @return ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'redis';
    }
}
