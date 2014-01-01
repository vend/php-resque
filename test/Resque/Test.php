<?php

namespace Resque;

use Resque\Test\ClientFactory;

abstract class Test extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Resque
     */
    protected $resque;

    /**
     * @var Client
     */
    protected $redis;

    public function setUp()
    {
        $factory = new ClientFactory();

        $this->redis = $factory->get();
        $this->redis->flushdb();

        $this->resque = new Resque($this->redis);
    }

    public function tearDown()
    {
        $this->redis->flushdb();
    }
}
