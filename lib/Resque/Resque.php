<?php

namespace Resque;

use Resque\Exception;
use Resque\Job;
use Resque\Job\Status;

/**
 * Base Resque class
 *
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
abstract class Resque
{
    const VERSION = '1.1-predis';

    abstract public function getClient();
    abstract public function reconnect();
    abstract public function log($message, $priority = 'info');

    /**
     * Push a job to the end of a specific queue. If the queue does not
     * exist, then create it as well.
     *
     * @param string $queue The name of the queue to add the job to.
     * @param array $item Job description as an array to be JSON encoded.
     */
    public function push($queue, $item)
    {
        $this->getClient()->sadd('queues', $queue);
        $this->getClient()->rpush('queue:' . $queue, json_encode($item));
    }

    /**
     * Pop an item off the end of the specified queue, decode it and
     * return it.
     *
     * @param string $queue The name of the queue to fetch an item from.
     * @return array Decoded item from the queue.
     */
    public function pop($queue)
    {
        $item = $this->getClient()->lpop('queue:' . $queue);
        if(!$item) {
            return;
        }
        return json_decode($item, true);
    }

    /**
     * Return the size (number of pending jobs) of the specified queue.
     *
     * @param $queue name of the queue to be checked for pending jobs
     *
     * @return int The size of the queue.
     */
    public function size($queue)
    {
        return $this->getClient()->llen('queue:' . $queue);
    }

    /**
     * Create a new job and save it to the specified queue.
     *
     * @param string $queue The name of the queue to place the job in.
     * @param string $class The name of the class that contains the code to execute the job.
     * @param array $args Any optional arguments that should be passed when the job is executed.
     * @param boolean $trackStatus Set to true to be able to monitor the status of a job.
     * @return string
     */
    public function enqueue($queue, $class, $args = null, $trackStatus = false)
    {
        if ($args !== null && !is_array($args)) {
            throw new InvalidArgumentException(
                'Supplied $args must be an array.'
            );
        }

        $id = md5(uniqid('', true));

        $this->push($queue, array(
            'class' => $class,
            'args'  => $args,
            'id'    => $id,
        ));

        if ($trackStatus) {
            Status::create($id);
        }

        return $id;
    }

    /**
     * Get an array of all known queues.
     *
     * @return array Array of queues.
     */
    public function queues()
    {
        $queues = $this->getClient()->smembers('queues');
        if(!is_array($queues)) {
            $queues = array();
        }
        return $queues;
    }

    /**
     * Gets a statistic
     *
     * @param string $name
     * @return \Resque\Statistic
     */
    public function getStatistic($name)
    {
        return new Statistic($this, $name);
    }
}
