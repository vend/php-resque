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
use Resque\Resque;

/**
 * Resque job.
 *
 * @package        Resque/Job
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
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
     * @var Resque The instance of Resque this job belongs to.
     */
    protected $resque;

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
     * @param Resque $resque
     */
    public function setResque(Resque $resque)
    {
        $this->resque = $resque;
    }

    /**
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Gets a status instance for this job
     *
     * @throws InvalidArgumentException
     * @throws JobLogicException
     * @return \Resque\Job\Status
     */
    protected function getStatus()
    {
        if (!$this->resque) {
            throw new JobLogicException('Job has no Resque instance: cannot get status');
        }

        return $this->resque->getStatusFactory()->forJob($this);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Execute the job
     *
     * @return bool
     */
    abstract public function perform();

    /**
     * Re-queue the current job.
     *
     * @return string ID of the recreated job
     */
    protected function recreate()
    {
        $status = $this->getStatus();
        $tracking = $status->isTracking();

        $new = $this->resque->enqueue(
            $this->queue,
            $this->payload['class'],
            $this->payload['args'],
            $tracking
        );

        if ($tracking) {
            $status->update(Status::STATUS_RECREATED);
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
