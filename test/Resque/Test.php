<?php

namespace Resque;

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

        $this->resque = new Resque($this->redis);
    }

    public function tearDown()
    {
        $this->redis->flushdb();
    }
}
