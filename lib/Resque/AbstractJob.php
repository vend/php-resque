<?php

namespace Resque;

use \ArrayAccess;
use \ArrayIterator;
use Exception;
use \InvalidArgumentException;
use \IteratorAggregate;
use Resque\Exception\JobLogicException;
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
     * @var string The ID of the job
     */
    protected $id;

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
        $this->id = isset($payload['id']) ? $payload['id'] : null;
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
        if (!isset($this->id)) {
            throw new InvalidArgumentException('Cannot get status instance: payload has no ID');
        }

        if (!$this->worker) {
            throw new JobLogicException('Job has no worker: cannot get status');
        }

        return new Status($this->id, $this->worker->getResque());
    }

    public function getId()
    {

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
     * Mark this job as having failed.
     *
     * @param $exception
     */
    public function fail(Exception $exception)
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

        if(!empty($this->id)) {
            $name[] = 'ID: ' . $this->id;
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
