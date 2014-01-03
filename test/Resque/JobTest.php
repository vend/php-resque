<?php

namespace Resque;

use Resque\Test;

/**
 * Job tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class JobTest extends Test
{
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
		$this->assertTrue((bool)$this->resque->enqueue('jobs', 'Test_Job'));
	}

	public function testQeueuedJobCanBeReserved()
	{
		$this->resque->enqueue('jobs', 'Test_Job');

        $worker = new Worker($this->resque, 'jobs');
        $worker->reserve();

		$job = $worker->reserve('jobs');
		if($job == false) {
			$this->fail('Job could not be reserved.');
		}
		$this->assertEquals('jobs', $job->queue);
		$this->assertEquals('Test_Job', $job->payload['class']);
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testObjectArgumentsCannotBePassedToJob()
	{
		$args = new \stdClass;
		$args->test = 'somevalue';
		$this->resque->enqueue('jobs', 'Test_Job', $args);
	}

	public function testFailedJobExceptionsAreCaught()
	{
		$payload = array(
			'class' => 'Failing_Job',
			'args' => null
		);
		$job = new Job('jobs', $payload);
		$job->worker = $this->worker;

		$this->worker->perform($job);

		$this->assertEquals(1, Statistic::get('failed'));
		$this->assertEquals(1, Statistic::get('failed:'.$this->worker));
	}

	/**
	 * @expectedException Resque\Exception
	 */
	public function testJobWithoutPerformMethodThrowsException()
	{
		$this->resque->enqueue('jobs', 'Test_Job_Without_Perform_Method');
		$job = $this->worker->reserve();
		$job->worker = $this->worker;
		$job->perform();
	}

	/**
	 * @expectedException Resque\Exception
	 */
	public function testInvalidJobThrowsException()
	{
		$this->resque->enqueue('jobs', 'Invalid_Job');
		$job = $this->worker->reserve();
		$job->worker = $this->worker;
		$job->perform();
	}

	public function testJobWithSetUpCallbackFiresSetUp()
	{
		$payload = array(
			'class' => 'Test_Job_With_SetUp',
			'args' => array(
				'somevar',
				'somevar2',
			),
		);
		$job = new Job('jobs', $payload);
		$job->perform();
	}

	public function testJobWithTearDownCallbackFiresTearDown()
	{
		$payload = array(
			'class' => 'Test_Job_With_TearDown',
			'args' => array(
				'somevar',
				'somevar2',
			),
		);
		$job = new Job('jobs', $payload);
		$job->perform();
	}
}
