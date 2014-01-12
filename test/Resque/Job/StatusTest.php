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

        $worker = $this->getWorker('jobs');
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
        $this->resque->clearQueue('jobs');

        $token = $this->resque->enqueue('jobs', 'Resque\Test\FailingJob', null, true);

        $worker = $this->getWorker('jobs');

        $job = $worker->reserve();
        $worker->workingOn($job);

        $status = new Status($token, $this->resque);
        $this->assertEquals(Status::STATUS_RUNNING, $status->get());
    }

    public function testFailedJobReturnsFailedStatus()
    {
        $this->resque->clearQueue('jobs');

        $token = $this->resque->enqueue('jobs', 'Resque\Test\FailingJob', null, true);

        $worker = $this->getWorker('jobs');

        $status = new Status($token, $this->resque);
        $before = $status->get();

        $worker->work(0);

        $status = new Status($token, $this->resque);
        $after = $status->get();

        $status = new Status($token, $this->resque);
        $all = $status->getAll();
        $after2 = $status->get();

        $this->assertEquals(Status::STATUS_FAILED, $status->get());
    }

    public function testCompletedJobReturnsCompletedStatus()
    {
        $this->resque->clearQueue('jobs');

        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        $worker = $this->getWorker('jobs');
        $worker->work(0);

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
        $worker = $this->getWorker('jobs');
        $originalToken = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        /* @var $job Job */
        $job = $worker->reserve();

        // Mark this job as being worked on to ensure that the new status is still
        // waiting.
        $worker->workingOn($job);

        // Now recreate it
        $newToken = $job->recreate();

        // Make sure we've got a new job returned
        $this->assertNotEquals($originalToken, $newToken);

        // Now check the status of the new job
        /* @var $newJob Resque\Test\Job */
        $newJob = $worker->reserve();
        $this->assertEquals(Status::STATUS_WAITING, $newJob->getStatus()->get());
    }
}
