<?php

namespace Resque;

use Resque\Test;
use Resque\Test\FailingJob;

/**
 * Job tests.
 *
 * @package        Resque/Tests
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
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
        $this->worker->setLogger($this->logger);
        $this->worker->register();
    }

    public function testJobCanBeQueued()
    {
        $this->assertTrue((bool)$this->resque->enqueue('jobs', 'Resque\Test\Job'));
    }

    public function testQueuedJobCanBeReserved()
    {
        $this->resque->enqueue('jobs', 'Resque\Test\Job');

        $worker = $this->getWorker('jobs');

        $job = $worker->reserve('jobs');

        if ($job == false) {
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
        $this->resque->clearQueue('jobs');

        $job = new FailingJob('jobs', array(
            'class' => 'Resque\Test\FailingJob',
            'args'  => null,
            'id'    => 'failing_test_job'
        ));
        $job->setResque($this->resque);

        $this->worker->perform($job);

        $failed = new Statistic($this->resque, 'failed');
        $workerFailed = new Statistic($this->resque, 'failed:' . (string)$this->worker);

        $this->assertEquals(1, $failed->get());
        $this->assertEquals(1, $workerFailed->get());
    }

    /**
     * @expectedException \Resque\ResqueException
     */
    public function testInvalidJobThrowsException()
    {
        $this->resque->enqueue('jobs', 'Resque\Test\NoPerformJob');
        $job = $this->worker->reserve();
        $job->perform();
    }
}
