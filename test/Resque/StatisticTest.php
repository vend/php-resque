<?php

namespace Resque;

/**
 * Statistic tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class StatisticTest extends \PHPUnit_Framework_TestCase
{
	public function testStatCanBeIncremented()
	{
		Statistic::incr('test_incr');
		Statistic::incr('test_incr');
		$this->assertEquals(2, $this->redis->get('resque:stat:test_incr'));
	}

	public function testStatCanBeIncrementedByX()
	{
		Statistic::incr('test_incrX', 10);
		Statistic::incr('test_incrX', 11);
		$this->assertEquals(21, $this->redis->get('resque:stat:test_incrX'));
	}

	public function testStatCanBeDecremented()
	{
		Statistic::incr('test_decr', 22);
		Statistic::decr('test_decr');
		$this->assertEquals(21, $this->redis->get('resque:stat:test_decr'));
	}

	public function testStatCanBeDecrementedByX()
	{
		Statistic::incr('test_decrX', 22);
		Statistic::decr('test_decrX', 11);
		$this->assertEquals(11, $this->redis->get('resque:stat:test_decrX'));
	}

	public function testGetStatByName()
	{
		Statistic::incr('test_get', 100);
		$this->assertEquals(100, Statistic::get('test_get'));
	}

	public function testGetUnknownStatReturns0()
	{
		$this->assertEquals(0, Statistic::get('test_get_unknown'));
	}
}
