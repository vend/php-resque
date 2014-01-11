<?php

namespace Resque;

/**
 * Statistic tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class StatisticTest extends Test
{
    /**
     * @var Statistic
     */
    protected $statistic;

    public function setUp()
    {
        parent::setUp();

        $this->statistic = new Statistic($this->resque, __CLASS__);
    }

    public function tearDown()
    {
        $this->statistic->clear();
        $this->statistic = null;
    }

    protected function assertStatisticValueByClient($value, $message = '')
    {
        $this->assertEquals($value, $this->redis->get('resque:stat:test'), $message);
    }

	public function testStatCanBeIncremented()
	{
        $this->statistic->incr();
        $this->statistic->incr();
        $this->assertStatisticValueByClient(2);
	}

	public function testStatCanBeIncrementedByX()
	{
        $this->statistic->incr(10);
        $this->statistic->incr(11);
        $this->assertStatisticValueByClient(21);
	}

	public function testStatCanBeDecremented()
	{
        $this->statistic->incr(22);
        $this->statistic->decr();
        $this->assertStatisticValueByClient(21);
	}

	public function testStatCanBeDecrementedByX()
    {
        $this->statistic->incr(22);
        $this->statistic->decr(11);
        $this->assertStatisticValueByClient(11);
	}

	public function testGetStatByName()
	{
        $this->statistic->incr(100);
		$this->assertEquals(100, $this->statistic->get());
	}

	public function testGetUnknownStatReturns0()
	{
        $statistic = new Statistic($this->resque, 'some_unknown_statistic');
		$this->assertEquals(0, $statistic->get());
	}
}
