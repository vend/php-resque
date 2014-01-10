<?php

namespace Resque;

use Resque\Test;
use Resque\Test\Job;

/**
 * Job tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class AbstractJobTest extends Test
{
    /**
     * @var Worker
     */
    protected $worker;

	public function setUp()
	{
		parent::setUp();

		// Register a worker to test with
		$this->worker = new Worker($this->resque, 'jobs');
		$this->worker->setLogger(new Log());
		$this->worker->register();
	}

	public function testJobCanBeQueued()
	{
		$this->assertTrue((bool)$this->resque->enqueue('jobs', 'Resque\Test\Job'));
	}

	public function testQueuedJobCanBeReserved()
	{
		$this->resque->enqueue('jobs', 'Resque\Test\Job');

        $worker = new Worker($this->resque, 'jobs');
        $worker->reserve();

		$job = $worker->reserve('jobs');

		if($job == false) {
			$this->fail('Job could not be reserved.');
		}

		$this->assertEquals('jobs', $job->getQueue());
		$this->assertEquals('Resque\Test\Job', $job['class']);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testObjectArgumentsCannotBePassedToJob()
	{
		$args = new \stdClass;
		$args->test = 'somevalue';
		$this->resque->enqueue('jobs', 'Resque\Test\Job', $args);
	}

	public function testFailedJobExceptionsAreCaught()
	{
		$payload = array(
			'class' => 'Failing_Job',
			'args' => null
		);
		$job = new Job('jobs', $payload);
		$job->setWorker($this->worker);

		$this->worker->perform($job);

		$this->assertEquals(1, Statistic::get('failed'));
		$this->assertEquals(1, Statistic::get('failed:'.$this->worker));
	}

	/**
	 * @expectedException Resque\Exception
	 */
	public function testInvalidJobThrowsException()
	{
		$this->resque->enqueue('jobs', 'Resque\Test\NoPerformJob');
		$job = $this->worker->reserve();
		$job->worker = $this->worker;
		$job->perform();
	}
}
