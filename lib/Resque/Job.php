<?php

namespace Resque;


use \ArrayAccess;
use \ArrayIterator;
use \IteratorAggregate;
use Resque\Failure;
use Resque\Job\Status;
use Resque\Job\DontPerformException;

/**
 * Resque job.
 *
 * @author        Chris Boulton <chris.boulton@interspire.com>
 * @copyright    (c) 2010 Chris Boulton
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class Job implements ArrayAccess, IteratorAggregate
{
    /**
     * @var string The name of the queue that this job belongs to.
     */
    public $queue;

    /**
     * @todo Mark protected
     * @var Worker Instance of the Resque worker running this job.
     */
    public $worker;

    /**
     * @todo Mark protected
     * @var array Containing details of the job.
     */
    public $payload;

    /**
     * @var object Instance of the class performing work for this job.
     */
    private $instance;

    /**
     * Instantiate a new instance of a job.
     *
     * @param string $queue The queue that the job belongs to.
     * @param array $payload array containing details of the job.
     */
    public function __construct($queue, $payload)
    {
        $this->queue = $queue;
        $this->payload = $payload;
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
     * Get the instantiated object for this job that will be performing work.
     *
     * @return object Instance of the object that this job belongs to.
     */
    public function getInstance()
    {
        if (!is_null($this->instance)) {
            return $this->instance;
        }

        if(!class_exists($this->payload['class'])) {
            throw new Exception(
                'Could not find job class ' . $this->payload['class'] . '.'
            );
        }

        if(!method_exists($this->payload['class'], 'perform')) {
            throw new Exception(
                'Job class ' . $this->payload['class'] . ' does not contain a perform method.'
            );
        }

        $this->instance = new $this->payload['class']();
        $this->instance->job = $this;
        $this->instance->args = $this->getArguments();
        $this->instance->queue = $this->queue;
        return $this->instance;
    }

    /**
     * Actually execute a job by calling the perform method on the class
     * associated with the job with the supplied arguments.
     *
     * @return bool
     * @throws Exception When the job's class could not be found or it does not contain a perform method.
     */
    public function perform()
    {
        $instance = $this->getInstance();
        try {
            $this->worker->notifyEvent('beforePerform', $this);

            if (method_exists($instance, 'setUp')) {
                $instance->setUp();
            }

            $instance->perform();

            if (method_exists($instance, 'tearDown')) {
                $instance->tearDown();
            }

            $this->worker->notifyEvent('afterPerform', $this);
        }
        // beforePerform/setUp have said don't perform this job. Return.
        catch(DontPerformException $e) {
            return false;
        }

        return true;
    }

    /**
     * Mark the current job as having failed.
     *
     * @param $exception
     */
    public function fail($exception)
    {
        $this->worker->notifyEvent('onFailure', array(
            'exception' => $exception,
            'job' => $this,
        ));

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
     * @return string
     */
    public function recreate()
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
