<?php

namespace Resque;

use Psr\Log\LoggerInterface;
use Resque\Test\Settings;

abstract class Test extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Settings
     */
    protected static $settings = null;

    /**
     * @var Resque
     */
    protected $resque;

    /**
     * @var Client
     */
    protected $redis;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Settings $settings
     */
    public static function setSettings(Settings $settings)
    {
        self::$settings = $settings;
    }

    public function setUp()
    {
        if (!self::$settings) {
            throw new \LogicException('You must supply the test case with a settings instance');
        }

        $this->redis = self::$settings->getClient();
        $this->redis->flushdb();

        $this->logger = self::$settings->getLogger();

        $this->resque = new Resque($this->redis);
        $this->resque->setLogger($this->logger);
    }

    public function tearDown()
    {
        $this->redis->flushdb();
    }

    protected function getWorker($queues)
    {
        $worker = new Worker($this->resque, $queues);
        $worker->setLogger($this->logger);
        $worker->register();

        return $worker;
    }
}
