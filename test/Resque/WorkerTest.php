<?php

namespace Resque;

/**
 * Resque_Worker tests.
 *
 * @package		Resque/Tests
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
		$this->assertEquals($num, count(Resque_Worker::all()));
	}

	public function testGetWorkerById()
	{
		$worker = new Worker($this->resque, '*');
        $worker->setLogger(new Log());
		$worker->register();

		$newWorker = Worker::find((string)$worker);
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

		$this->assertFalse(Worker::exists((string)$worker));
		$this->assertEquals(array(), Worker::all());
		$this->assertEquals(array(), $this->redis->smembers('resque:workers'));
	}

	public function testPausedWorkerDoesNotPickUpJobs()
	{
		$worker = new Worker($this->resque, '*');
		$worker->setLogger(new Log());
		$worker->pauseProcessing();
		$this->resque->enqueue('jobs', 'Test_Job');
		$worker->work(0);
		$worker->work(0);
		$this->assertEquals(0, $worker->getStatistic('processed'));
	}

	public function testResumedWorkerPicksUpJobs()
	{
		$worker = new Worker($this->resque, '*');
		$worker->setLogger(new Log());
		$worker->pauseProcessing();
		Resque::enqueue('jobs', 'Test_Job');
		$worker->work(0);
		$this->assertEquals(0, $worker->getStatistic('processed'));
		$worker->unPauseProcessing();
		$worker->work(0);
		$this->assertEquals(1, $worker->getStatistic('processed'));
	}

	public function testWorkerCanWorkOverMultipleQueues()
	{
		$worker = new Worker($this->resque, array(
			'queue1',
			'queue2'
		));

        $worker->setLogger(new Log());
		$worker->register();

        $this->resque->enqueue('queue1', 'Test_Job_1');
		$this->resque->enqueue('queue2', 'Test_Job_2');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->queue);
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
		$this->resque->enqueue('low', 'Test_Job_1');
        $this->resque->enqueue('high', 'Test_Job_2');
        $this->resque->enqueue('medium', 'Test_Job_3');

		// Now check we get the jobs back in the right order
		$job = $worker->reserve();
		$this->assertEquals('high', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('medium', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('low', $job->queue);
	}

	public function testWildcardQueueWorkerWorksAllQueues()
	{
		$worker = new Worker($this->resque, '*');
        $worker->setLogger(new Log());
		$worker->register();
		

		Resque::enqueue('queue1', 'Test_Job_1');
		Resque::enqueue('queue2', 'Test_Job_2');

		$job = $worker->reserve();
		$this->assertEquals('queue1', $job->queue);

		$job = $worker->reserve();
		$this->assertEquals('queue2', $job->queue);
	}

	public function testWorkerDoesNotWorkOnUnknownQueues()
	{
		$worker = new Worker($this->resque, 'queue1');
        $worker->setLogger(new Log());
		$worker->register();
		Resque::enqueue('queue2', 'Test_Job');

		$this->assertFalse($worker->reserve());
	}

	public function testWorkerClearsItsStatusWhenNotWorking()
	{
        $this->resque->enqueue('jobs', 'Test_Job');
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
			'class' => 'Test_Job'
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
        $this->resque->enqueue('jobs', 'Test_Job');
        $this->resque->enqueue('jobs', 'Invalid_Job');

		$worker = new Worker($this->resque, 'jobs');
		$worker->setLogger(new Log());
		$worker->work(0);
		$worker->work(0);

		$this->assertEquals(0, $worker->getStatistic('processed'));
		$this->assertEquals(0, $worker->getStatistic('failed'));
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

		$this->assertEquals(3, count(Resque_Worker::all()));

		$goodWorker->pruneDeadWorkers();

		// There should only be $goodWorker left now
		$this->assertEquals(1, count(Resque_Worker::all()));
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

		$this->assertEquals(2, count(Resque_Worker::all()));

		$worker->pruneDeadWorkers();

		// my.other.host should be left
		$workers = Resque_Worker::all();
		$this->assertEquals(1, count($workers));
		$this->assertEquals((string)$worker, (string)$workers[0]);
	}

	public function testWorkerFailsUncompletedJobsOnExit()
	{
		$worker = new Worker($this->resque, 'jobs');
        $worker->setLogger(new Log());
		$worker->register();

		$payload = array(
			'class' => 'Test_Job'
		);
		$job = new Job('jobs', $payload);

		$worker->workingOn($job);
		$worker->unregister();

		$this->assertEquals(1, $worker->getStatistic('failed'));
	}

    public function testBlockingListPop()
    {
        $worker = new Worker($this->resque, 'jobs');
		$worker->setLogger(new Log());
        $worker->register();

        $this->resque->enqueue('jobs', 'Test_Job_1');
        $this->resque->enqueue('jobs', 'Test_Job_2');

        $i = 1;
        while($job = $worker->reserve(true, 1))
        {
            $this->assertEquals('Test_Job_' . $i, $job->payload['class']);

            if($i == 2) {
                break;
            }

            $i++;
        }

        $this->assertEquals(2, $i);
    }
}
