<?php

namespace Resque\Tests\Mock;

use Resque\Tests\TestCase;
use Resque\Resque as BaseResque;

/**
 * Mock resque class
 */
class Resque extends BaseResque
{
    /**
     * @var TestCase
     */
    protected $case;

    public function __construct(TestCase $case)
    {
        $this->case = $case;
    }

    public function getClient()
    {
        return $this;
    }

    public function log($message, $priority = 'info')
    {}

    public function reconnect()
    {}

    public function getKey($key)
    {
        return $key;
    }

    public function __call($method, $arguments)
    {}
}