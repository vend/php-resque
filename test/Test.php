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
     * @var ClientInterface
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
        if ($this->redis) {
            $this->redis->flushdb();

            if ($this->redis->isConnected()) {
                $this->logger->notice('Shutting down connected Redis instance in tearDown()');
                $this->redis->disconnect();
            }
        }
    }

    protected function getWorker($queues)
    {
        $worker = new Worker($this->resque, $queues);
        $worker->setLogger($this->logger);
        $worker->register();

        return $worker;
    }
}
