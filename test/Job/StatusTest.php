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
    public function tearDown()
    {
        parent::tearDown();

        $this->resque->clearQueue('jobs');
    }

    public function testConstructor()
    {
        $status = new Status(uniqid(__CLASS__, true), $this->resque);
    }

    public function testJobStatusCanBeTracked()
    {
        $this->resque->clearQueue('jobs');
        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        $status = new Status($token, $this->resque);
        $this->assertTrue($status->isTracking());
    }

    public function testJobStatusIsReturnedViaJobInstance()
    {
        $this->resque->clearQueue('jobs');

        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        $worker = $this->getWorker('jobs');
        $job = $worker->reserve();

        if (!$job) {
            $this->fail('Could not get job');
        }

        $status = $this->resque->getStatusFactory()->forJob($job);
        $status = $status->get();

        $this->assertEquals(Status::STATUS_WAITING, $status);
    }

    public function testQueuedJobReturnsQueuedStatus()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);
        $status = new Status($token, $this->resque);
        $this->assertEquals(Status::STATUS_WAITING, $status->get());
    }

    public function testQueuedJobReturnsCreatedAndUpdatedKeys()
    {
        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);
        $status = new Status($token, $this->resque);
        $this->assertGreaterThan(0, $status->getCreated());
        $this->assertGreaterThan(0, $status->getUpdated());
    }

    public function testStartingQueuedJobUpdatesUpdatedAtStatus()
    {
        $this->resque->clearQueue('jobs');

        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        $status = new Status($token, $this->resque);
        $old_created = $status->getCreated();
        $old_updated = $status->getUpdated();

        sleep(1);

        $worker = $this->getWorker('jobs');

        $job = $worker->reserve();

        if (!$job) {
            $this->fail('Cannot get job');
        }

        $worker->workingOn($job);

        $status = new Status($token, $this->resque);
        $this->assertEquals(Status::STATUS_RUNNING, $status->get());
        $this->assertEquals($old_created, $status->getCreated());
        $this->assertGreaterThan($old_updated, $status->getUpdated());
    }

    public function testRunningJobReturnsRunningStatus()
    {
        $this->resque->clearQueue('jobs');

        $token = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        $worker = $this->getWorker('jobs');

        $job = $worker->reserve();

        if (!$job) {
            $this->fail('Cannot get job');
        }

        $worker->workingOn($job);

        $status = new Status($token, $this->resque);
        $this->assertEquals(Status::STATUS_RUNNING, $status->get());
    }

    public function testFailedJobReturnsFailedStatus()
    {
        $pid = getmypid();

        $this->resque->clearQueue('jobs');

        $token = $this->resque->enqueue('jobs', 'Resque\Test\FailingJob', null, true);

        $worker = $this->getWorker('jobs');

        $status = new Status($token, $this->resque);
        $before = $status->get();

        $pid2 = getmypid();

        $worker->work(0);

        $pid3 = getmypid();
        $status = new Status($token, $this->resque);
        $after = $status->get();

        $this->assertEquals(Status::STATUS_FAILED, $after);
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

    /*
    public function testRecreatedJobWithTrackingStillTracksStatus()
    {
        $worker = $this->getWorker('jobs');
        $originalToken = $this->resque->enqueue('jobs', 'Resque\Test\Job', null, true);

        $job = $worker->reserve();

        if (!$job) {
            $this->fail('Could not reserve job');
        }

        // Mark this job as being worked on to ensure that the new status is still
        // waiting.
        $worker->workingOn($job);

        // Now recreate it
        $newToken = $job->recreate();

        // Make sure we've got a new job returned
        $this->assertNotEquals($originalToken, $newToken);

        // Now check the status of the new job
        $newJob = $worker->reserve();

        if (!$newJob) {
            $this->fail('Could not get newJob');
        }

        $this->assertEquals(Status::STATUS_WAITING, $newJob->getStatus()->get());
    }
    */
}
