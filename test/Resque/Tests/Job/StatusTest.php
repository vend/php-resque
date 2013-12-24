<?php
<<<<<<< Updated upstream:lib/Resque/Tests/Job/StatusTest.php
=======
<<<<<<< HEAD:lib/Resque/Tests/JobStatusTest.php
require_once dirname(__FILE__) . '/bootstrap.php';
>>>>>>> Stashed changes:lib/Resque/Tests/JobStatusTest.php

namespace Resque\Tests\Job;

use Resque\Tests\TestCase;

use Resque\Tests\Mock\Resque;
use Resque\Job\Status;

=======
>>>>>>> chrisboulton/master:test/Resque/Tests/JobStatusTest.php
/**
 * Status tests.
 *
 * @package		Resque/Tests
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class StatusTest extends TestCase
{
    /**
     * @var \Resque_Worker
     */
    protected $worker;

	public function setUp()
	{
		parent::setUp();

<<<<<<< Updated upstream:lib/Resque/Tests/Job/StatusTest.php
		$this->resque = new Resque();
	}

	public function testConstructor()
	{
        $status = new Status(md5(time()), $this->resque);
=======
		// Register a worker to test with
		$this->worker = new Resque_Worker('jobs');
		$this->worker->setLogger(new Resque_Log());
>>>>>>> Stashed changes:lib/Resque/Tests/JobStatusTest.php
	}

	public function testJobStatusCanBeTracked()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', null, true);
		$status = new Status($token);
		$this->assertTrue($status->isTracking());
	}

	public function testJobStatusIsReturnedViaJobInstance()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', null, true);
		$job = Resque_Job::reserve('jobs');
		$this->assertEquals(Status::STATUS_WAITING, $job->getStatus());
	}

	public function testQueuedJobReturnsQueuedStatus()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', null, true);
		$status = new Status($token);
		$this->assertEquals(Status::STATUS_WAITING, $status->get());
	}

	public function testRunningJobReturnsRunningStatus()
	{
		$token = Resque::enqueue('jobs', 'Failing_Job', null, true);
		$job = $this->worker->reserve();
		$this->worker->workingOn($job);
		$status = new Status($token);
		$this->assertEquals(Status::STATUS_RUNNING, $status->get());
	}

	public function testFailedJobReturnsFailedStatus()
	{
		$token = Resque::enqueue('jobs', 'Failing_Job', null, true);
		$this->worker->work(0);
		$status = new Status($token);
		$this->assertEquals(Status::STATUS_FAILED, $status->get());
	}

	public function testCompletedJobReturnsCompletedStatus()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', null, true);
		$this->worker->work(0);
		$status = new Status($token);
		$this->assertEquals(Status::STATUS_COMPLETE, $status->get());
	}

	public function testStatusIsNotTrackedWhenToldNotTo()
	{
		$token = Resque::enqueue('jobs', 'Test_Job', null, false);
		$status = new Status($token);
		$this->assertFalse($status->isTracking());
	}

	public function testStatusTrackingCanBeStopped()
	{
		Status::create('test');
		$status = new Status('test');
		$this->assertEquals(Status::STATUS_WAITING, $status->get());
		$status->stop();
		$this->assertFalse($status->get());
	}

	public function testRecreatedJobWithTrackingStillTracksStatus()
	{
		$originalToken = Resque::enqueue('jobs', 'Test_Job', null, true);
		$job = $this->worker->reserve();

		// Mark this job as being worked on to ensure that the new status is still
		// waiting.
		$this->worker->workingOn($job);

		// Now recreate it
		$newToken = $job->recreate();

		// Make sure we've got a new job returned
		$this->assertNotEquals($originalToken, $newToken);

		// Now check the status of the new job
		$newJob = Resque_Job::reserve('jobs');
		$this->assertEquals(Status::STATUS_WAITING, $newJob->getStatus());
	}
}
