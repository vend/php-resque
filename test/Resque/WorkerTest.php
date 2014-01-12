<?php

namespace Resque;

use Resque\Test\Job;

/**
 * Resque_Worker tests.
 *
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class WorkerTest extends Test
{
    public function testWorkerRegistersInList()
    {
        $worker = $this->getWorker('*');

        // Make sure the worker is in the list
        $this->assertTrue((bool)$this->redis->sismember('resque:workers', (string)$worker));
    }

    public function testGetAllWorkers()
    {
        $num = 3;
        // Register a few workers
        for($i = 0; $i < $num; ++$i) {
            $worker = $this->getWorker('queue_' . $i);
        }

        // Now try to get them
        $this->assertEquals($num, count($this->resque->getWorkerIds()));
    }

    public function testGetWorkerById()
    {
        $worker = $this->getWorker('*');

        $newWorker = new Worker($this->resque, '*');
        $newWorker->setId((string)$worker);

        $this->assertEquals((string)$worker, (string)$newWorker);
    }


    public function testWorkerCanUnregister()
    {
        $worker = $this->getWorker('*');
        $worker->unregister();

        $this->assertFalse($this->resque->workerExists((string)$worker));
        $this->assertEquals(array(), $this->resque->getWorkerIds());
        $this->assertEquals(array(), $this->redis->smembers('resque:workers'));
    }

    public function testPausedWorkerDoesNotPickUpJobs()
    {
        $worker = $this->getWorker('*');
        $worker->pauseProcessing();
        $this->resque->enqueue('jobs', 'Resque\Test\Job');
        $worker->work(0);
        $worker->work(0);
        $this->assertEquals(0, $worker->getStatistic('processed')->get());
    }

    public function testResumedWorkerPicksUpJobs()
    {
        $worker = $this->getWorker('*');
        $worker->pauseProcessing();
        $this->resque->enqueue('jobs', 'Resque\Test\Job');
        $worker->work(0);
        $this->assertEquals(0, $a = $worker->getStatistic('processed')->get());
        $worker->unPauseProcessing();
        $worker->work(0);
        $worker->getStatistic('processed')->get();
        $this->assertEquals(1, $b = $worker->getStatistic('processed')->get());
    }

    public function testWorkerCanWorkOverMultipleQueues()
    {
        $queues = array(
            'queue1',
            'queue2'
        );

        $worker = $this->getWorker($queues);

        $this->resque->enqueue($queues[0], 'Resque\Test\Job');
        $this->resque->enqueue($queues[1], 'Resque\Test\Job');

        $job = $worker->reserve();
        $this->assertTrue(in_array($job->getQueue(), $queues), 'Job from valid queues');

        $job = $worker->reserve();
        $this->assertTrue(in_array($job->getQueue(), $queues), 'Job from valid queues');
    }

    public function testWildcardQueueWorkerWorksAllQueues()
    {
        $queues = array(
            'queue1',
            'queue2'
        );

        $worker = $this->getWorker('*');

        $this->resque->enqueue($queues[0], 'Resque\Test\Job');
        $this->resque->enqueue($queues[1], 'Resque\Test\Job');

        $job = $worker->reserve();
        $this->assertTrue(in_array($job->getQueue(), $queues), 'Job from valid queues');

        $job = $worker->reserve();
        $this->assertTrue(in_array($job->getQueue(), $queues), 'Job from valid queues');
    }

    public function testWorkerDoesNotWorkOnUnknownQueues()
    {
        $worker = $this->getWorker('queue1');

        $this->resque->enqueue('queue2', 'Resque\Test\Job');

        $this->assertNull($worker->reserve());
    }

    public function testWorkerClearsItsStatusWhenNotWorking()
    {
        $this->resque->enqueue('jobs', 'Resque\Test\Job');
        $worker = $this->getWorker('jobs');
        $job = $worker->reserve();
        $worker->workingOn($job);
        $worker->doneWorking();
        $this->assertEquals(array(), $worker->job());
    }

    public function testWorkerRecordsWhatItIsWorkingOn()
    {
        $worker = $this->getWorker('jobs');

        $payload = array(
            'class' => 'Resque\Test\Job',
            'id'    => 'test'
        );

        $job = new Job('jobs', $payload);
        $worker->workingOn($job);

        $job = $worker->job();
        $this->assertEquals('jobs', $job['queue']);

        if(!isset($job['run_at'])) {
            $this->fail('Job does not have run_at time');
        }

        $this->assertEquals($payload, $job['payload']);
    }

    public function testWorkerErasesItsStatsWhenShutdown()
    {
        $this->resque->enqueue('jobs', 'Resque\Test\Job');
        $this->resque->enqueue('jobs', 'Resque\Test\FailingJob');

        $worker = $this->getWorker('jobs');

        $worker->work(0);
        $worker->shutdown();
        $worker->work(0);

        $this->resque->clearQueue('jobs');

        $this->assertEquals(0, $worker->getStatistic('processed')->get());
        $this->assertEquals(0, $worker->getStatistic('failed')->get());
    }

    public function testWorkerCleansUpDeadWorkersOnStartup()
    {
        // Register a good worker
        $goodWorker = new Worker($this->resque, 'jobs');
        $goodWorker->setLogger($this->logger);
        $goodWorker->register();
        $goodWorker = $this->getWorker('jobs');

        $workerId = explode(':', $goodWorker);

        // Register some bad workers
        $worker = new Worker($this->resque, 'jobs');
        $worker->setLogger($this->logger);
        $worker->setId($workerId[0].':1:jobs');
        $worker->register();

        $worker = new Worker($this->resque, array('high', 'low'));
        $worker->setLogger($this->logger);
        $worker->setId($workerId[0].':2:high,low');
        $worker->register();

        $this->assertEquals(3, count($this->resque->getWorkerIds()));

        $goodWorker->pruneDeadWorkers();

        // There should only be $goodWorker left now
        $this->assertEquals(1, count($this->resque->getWorkerIds()));
    }

    public function testDeadWorkerCleanUpDoesNotCleanUnknownWorkers()
    {
        // Register a bad worker on this machine
        $worker = new Worker($this->resque, 'jobs');
        $worker->setLogger($this->logger);
        $workerId = explode(':', $worker);
        $worker->setId($workerId[0].':1:jobs');
        $worker->register();

        // Register some other false workers
        $worker = new Worker($this->resque, 'jobs');
        $worker->setLogger($this->logger);
        $worker->setId('my.other.host:1:jobs');
        $worker->register();

        $this->assertEquals(2, count($this->resque->getWorkerIds()));

        $worker->pruneDeadWorkers();

        // my.other.host should be left
        $workers = $this->resque->getWorkerIds();
        $this->assertEquals(1, count($workers));
        $this->assertEquals((string)$worker, (string)$workers[0]);
    }

    public function testWorkerFailsUncompletedJobsOnExit()
    {
        $backend = $this->getMockForAbstractClass('Resque\Failure\BackendInterface');

        $backend->expects($this->once())
            ->method('receiveFailure');

        $this->resque->setFailureBackend($backend);

        $worker = $this->getWorker('jobs');

        $job = new Job('jobs', array(
            'class' => 'Resque\Test\Job',
            'id'    => __METHOD__
        ));
        $job->setResque($this->resque);

        $worker->workingOn($job);
        $worker->unregister();
    }

    public function testBlockingListPop()
    {
        $worker = $this->getWorker('jobs');

        $this->resque->enqueue('jobs', 'Resque\Test\Job');
        $this->resque->enqueue('jobs', 'Resque\Test\Job');

        $i = 1;
        while($job = $worker->reserve(true, 1))
        {
            $this->assertEquals('Resque\Test\Job', $job['class']);

            if($i == 2) {
                break;
            }

            $i++;
        }

        $this->assertEquals(2, $i);
    }

    public function testReestablishRedisConnection()
    {
        $worker = $this->getWorker('jobs');
        $worker->reestablishRedisConnection();
    }
}
