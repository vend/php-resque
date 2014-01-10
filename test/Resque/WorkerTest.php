<?php

namespace Resque;

use Resque\Test\Job;

/**
 * Resque_Worker tests.
 *
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class WorkerTest extends Test
{
	public function testWorkerRegistersInList()
	{
		$worker = new Worker($this->resque, '*');
		$worker->setLogger(new Log());
		$worker->register();

		// Make sure the worker is in the list
		$this->assertTrue((bool)$this->redis->sismember('resque:workers', (string)$worker));
	}

	public function testGetAllWorkers()
	{
		$num = 3;
		// Register a few workers
		for($i = 0; $i < $num; ++$i) {
			$worker = new Worker($this->resque, 'queue_' . $i);
			$worker->setLogger(new Log());
			$worker->register();
		}

		// Now try to get them
		$this->assertEquals($num, count($this->resque->getWorkerIds()));
	}

	public function testGetWorkerById()
	{
		$worker = new Worker($this->resque, '*');
        $worker->setLogger(new Log());
		$worker->register();

        $newWorker = new Worker($this->resque, '*');
        $newWorker->setId((string)$worker);

		$this->assertEquals((string)$worker, (string)$newWorker);
	}

	public function testInvalidWorkerDoesNotExist()
	{
		$this->assertFalse(Worker::exists('blah'));
	}

	public function testWorkerCanUnregister()
	{
		$worker = new Worker($this->resque, '*');
        $worker->setLogger(new Log());
		$worker->register();
		$worker->unregister();

		$this->assertFalse($this->resque->workerExists((string)$worker));
		$this->assertEquals(array(), $this->resque->getWorkerIds());
		$this->assertEquals(array(), $this->redis->smembers('resque:workers'));
	}

	public function testPausedWorkerDoesNotPickUpJobs()
	{
		$worker = new Worker($this->resque, '*');
		$worker->setLogger(new Log());
		$worker->pauseProcessing();
		$this->resque->enqueue('jobs', 'Resque\Test\Job');
		$worker->work(0);
		$worker->work(0);
		$this->assertEquals(0, $worker->getStatistic('processed')->get());
	}

	public function testResumedWorkerPicksUpJobs()
	{
		$worker = new Worker($this->resque, '*');
		$worker->setLogger(new Log());
		$worker->pauseProcessing();
		$this->resque->enqueue('jobs', 'Resque\Test\Job');
		$worker->work(0);
		$this->assertEquals(0, $worker->getStatistic('processed')->get());
		$worker->unPauseProcessing();
		$worker->work(0);
		$this->assertEquals(1, $worker->getStatistic('processed')->get());
	}

	public function testWorkerCanWorkOverMultipleQueues()
	{
		$worker = new Worker($this->resque, array(
			'queue1',
			'queue2'
		));

        $worker->setLogger(new Log());
		$worker->register();

        $this->resque->enqueue('queue1', 'Resque\Test\Job');
		$this->resque->enqueue('queue2', 'Resque\Test\Job');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->getQueue());

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->getQueue());
	}

	public function testWorkerWorksQueuesInSpecifiedOrder()
	{
		$worker = new Worker($this->resque, array(
			'high',
			'medium',
			'low'
		));
        $worker->setLogger(new Log());
		$worker->register();

		// Queue the jobs in a different order
		$this->resque->enqueue('low', 'Resque\Test\Job');
        $this->resque->enqueue('high', 'Resque\Test\Job');
        $this->resque->enqueue('medium', 'Resque\Test\Job');

		// Now check we get the jobs back in the right order
		$job = $worker->reserve();
		$this->assertEquals('high', $job->getQueue());

		$job = $worker->reserve();
		$this->assertEquals('medium', $job->getQueue());

		$job = $worker->reserve();
		$this->assertEquals('low', $job->getQueue());
	}

	public function testWildcardQueueWorkerWorksAllQueues()
	{
		$worker = new Worker($this->resque, '*');
        $worker->setLogger(new Log());
		$worker->register();
		

		$this->resque->enqueue('queue1', 'Resque\Test\Job');
		$this->resque->enqueue('queue2', 'Resque\Test\Job');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->getQueue());

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->getQueue());
	}

	public function testWorkerDoesNotWorkOnUnknownQueues()
	{
		$worker = new Worker($this->resque, 'queue1');
        $worker->setLogger(new Log());
		$worker->register();
		$this->resque->enqueue('queue2', 'Resque\Test\Job');

		$this->assertFalse($worker->reserve());
	}

	public function testWorkerClearsItsStatusWhenNotWorking()
	{
        $this->resque->enqueue('jobs', 'Resque\Test\Job');
		$worker = new Worker($this->resque, 'jobs');
		$worker->setLogger(new Log());
		$job = $worker->reserve();
		$worker->workingOn($job);
		$worker->doneWorking();
		$this->assertEquals(array(), $worker->job());
	}

	public function testWorkerRecordsWhatItIsWorkingOn()
	{
		$worker = new Worker($this->resque, 'jobs');
        $worker->setLogger(new Log());
		$worker->register();

		$payload = array(
			'class' => 'Resque\Test\Job'
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
        $this->resque->enqueue('jobs', 'Invalid_Job');

		$worker = new Worker($this->resque, 'jobs');
		$worker->setLogger(new Log());
		$worker->work(0);
		$worker->work(0);

		$this->assertEquals(0, $worker->getStatistic('processed')->get());
		$this->assertEquals(0, $worker->getStatistic('failed')->get());
	}

	public function testWorkerCleansUpDeadWorkersOnStartup()
	{
		// Register a good worker
		$goodWorker = new Worker($this->resque, 'jobs');
        $goodWorker->setLogger(new Log());
		$goodWorker->register();
		$workerId = explode(':', $goodWorker);

		// Register some bad workers
		$worker = new Worker($this->resque, 'jobs');
		$worker->setLogger(new Log());
		$worker->setId($workerId[0].':1:jobs');
		$worker->register();

		$worker = new Worker($this->resque, array('high', 'low'));
		$worker->setLogger(new Log());
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
		$worker->setLogger(new Log());
		$workerId = explode(':', $worker);
		$worker->setId($workerId[0].':1:jobs');
		$worker->register();

		// Register some other false workers
		$worker = new Worker($this->resque, 'jobs');
		$worker->setLogger(new Log());
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
		$worker = new Worker($this->resque, 'jobs');
        $worker->setLogger(new Log());
		$worker->register();

		$payload = array(
			'class' => 'Resque\Test\Job'
		);
		$job = new Job('jobs', $payload);

		$worker->workingOn($job);
		$worker->unregister();

		$this->assertEquals(1, $worker->getStatistic('failed')->get());
	}

    public function testBlockingListPop()
    {
        $worker = new Worker($this->resque, 'jobs');
		$worker->setLogger(new Log());
        $worker->register();

        $this->resque->enqueue('jobs', 'Resque\Test\Job');
        $this->resque->enqueue('jobs', 'Resque\Test\Job');

        $i = 1;
        while($job = $worker->reserve(true, 1))
        {
            $this->assertEquals('Resque\Test\Job', $job->payload['class']);

            if($i == 2) {
                break;
            }

            $i++;
        }

        $this->assertEquals(2, $i);
    }
}
