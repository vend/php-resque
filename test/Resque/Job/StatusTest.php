<?php

namespace Resque\Job;

use Predis\Client;
use Resque\Log;
use Resque\Resque;
use Resque\Test;
use Resque\TestCase;
use Resque\Worker;

/**
 * Status tests.
 *
 * @package        Resque/Tests
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class StatusTest extends Test
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
        $this->worker->setLogger($this->logger);
    }

    public function testConstructor()
    {
        $status = new Status(md5(time()), $this->resque);
    }

    public function testJobStatusCanBeTracked()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        $status = new Status($token, $this->resque);
        $this->assertTrue($status->isTracking());
    }

    public function testJobStatusIsReturnedViaJobInstance()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        $worker = new Worker($this->resque, 'jobs');
        $job = $worker->reserve();

        $this->assertEquals(Status::STATUS_WAITING, $job->getStatusCode());
    }

    public function testQueuedJobReturnsQueuedStatus()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);
        $status = new Status($token, $this->resque);
        $this->assertEquals(Status::STATUS_WAITING, $status->get());
    }

    public function testRunningJobReturnsRunningStatus()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\FailingJob', null, true);
        $job = $this->worker->reserve();
        $this->worker->workingOn($job);
        $status = new Status($token, $this->resque);
        $this->assertEquals(Status::STATUS_RUNNING, $status->get());
    }

    public function testFailedJobReturnsFailedStatus()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\FailingJob', null, true);
        $this->worker->work(0);
        $status = new Status($token, $this->resque);
        $this->assertEquals(Status::STATUS_FAILED, $status->get());
    }

    public function testCompletedJobReturnsCompletedStatus()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);
        $this->worker->work(0);
        $status = new Status($token, $this->resque);
        $this->assertEquals(Status::STATUS_COMPLETE, $status->get());
    }

    public function testStatusIsNotTrackedWhenToldNotTo()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, false);
        $status = new Status($token, $this->resque);
        $this->assertFalse($status->isTracking());
    }

    public function testStatusTrackingCanBeStopped()
    {
        $status = new Status('test', $this->resque);
        $status->create();
        $this->assertEquals(Status::STATUS_WAITING, $status->get());

        $status->stop();
        $this->assertNull($status->get());
    }

    public function testRecreatedJobWithTrackingStillTracksStatus()
    {
        $originalToken = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);
        $job = $this->worker->reserve();

        // Mark this job as being worked on to ensure that the new status is still
        // waiting.
        $this->worker->workingOn($job);

        // Now recreate it
        $newToken = $job->recreate();

        // Make sure we've got a new job returned
        $this->assertNotEquals($originalToken, $newToken);

        // Now check the status of the new job
        /* @var $newJob Resque\Test\Job */
        $newJob = $this->worker->reserve();
        $this->assertEquals(Status::STATUS_WAITING, $newJob->getStatus());
    }
}
