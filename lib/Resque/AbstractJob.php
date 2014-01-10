<?php

namespace Resque;

use \ArrayAccess;
use \ArrayIterator;
use \InvalidArgumentException;
use \IteratorAggregate;
use Resque\Failure;
use Resque\Job\Status;

/**
 * Resque job.
 *
 * @package		Resque/Job
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
abstract class AbstractJob implements ArrayAccess, IteratorAggregate, JobInterface
{
    /**
     * @var string The name of the queue that this job belongs to.
     */
    protected $queue;

    /**
     * @var Worker Instance of the Resque worker running this job.
     */
    protected $worker;

    /**
     * @var array Containing details of the job.
     */
    protected $payload;

    /**
     * Instantiate a new instance of a job.
     *
     * @param string $queue The queue that the job belongs to.
     * @param array $payload array containing details of the job.
     */
    public function __construct($queue, array $payload)
    {
        $this->queue = $queue;
        $this->payload = $payload;
    }

    /**
     * @param Worker $worker
     */
    public function setWorker(Worker $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Update the status of the current job.
     *
     * @param int $status Status constant from Resque\Job\Status indicating the current status of a job.
     */
    public function updateStatus($status)
    {
        if (empty($this->payload['id'])) {
            $this->worker->log('Unable to get job status: no ID in payload', 'warning');
            return;
        }
        $this->getStatusInstance()->update($status);
    }

    /**
     * Return the status of the current job.
     *
     * @return int The status of the job as one of the Status constants.
     */
    public function getStatus()
    {
        return $this->getStatusInstance()->get();
    }

    /**
     * Gets a status instance for this job
     *
     * @throws \InvalidArgumentException
     * @return \Resque\Job\Status
     */
    protected function getStatusInstance()
    {
        if (!isset($this->payload['id'])) {
            throw new InvalidArgumentException('Cannot get status instance: payload has no ID');
        }

        return new Status($this->payload['id'], $this->worker->getResque());
    }

    /**
     * Get the arguments supplied to this job.
     *
     * @return array Array of arguments.
     */
    public function getArguments()
    {
        if (!isset($this->payload['args'])) {
            return array();
        }

        return $this->payload['args'][0];
    }

    /**
     * Execute the job
     *
     * @return bool
     */
    abstract public function perform();

    /**
     * Mark the current job as having failed.
     *
     * @param $exception
     */
    protected function fail($exception)
    {
        $this->updateStatus(Status::STATUS_FAILED);

        Failure::create(
            $this->payload,
            $exception,
            $this->worker,
            $this->queue
        );

        $this->worker->getResque()->getStatistic('failed')->incr();
        $this->worker->getStatistic('failed')->incr();
    }

    /**
     * Re-queue the current job.
     *
     * @return string
     */
    protected function recreate()
    {
        $status = $this->getStatusInstance();
        $tracking = $status->isTracking();

        $new = $this->worker->getResque()->enqueue(
            $this->queue,
            $this->payload['class'],
            $this->payload['args'],
            $tracking
        );

        if ($tracking) {
            $this->updateStatus(Status::STATUS_RECREATED);
            $status->setAttribute('recreated_as', $new);
        }

        return $new;
    }

    /**
     * Generate a string representation used to describe the current job.
     *
     * @return string The string representation of the job.
     */
    public function __toString()
    {
        $name = array(
            'Job{' . $this->queue .'}'
        );

        if(!empty($this->payload['id'])) {
            $name[] = 'ID: ' . $this->payload['id'];
        }

        $name[] = $this->payload['class'];

        if(!empty($this->payload['args'])) {
            $name[] = json_encode($this->payload['args']);
        }

        return '(' . implode(' | ', $name) . ')';
    }

    /**
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->payload[] = $value;
        } else {
            $this->payload[$offset] = $value;
        }
    }

    /**
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        return isset($this->payload[$offset]);
    }

    /**
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        unset($this->payload[$offset]);
    }

    /**
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($offset)
    {
        return isset($this->payload[$offset]) ? $this->payload[$offset] : null;
    }

    /**
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->payload);
    }
}
