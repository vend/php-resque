<?php

namespace Resque;

use Resque\Exception;
use Resque\Job;
use Resque\Job\Status;
use Resque\Util\Log;

use \InvalidArgumentException;

/**
 * Base Resque class
 *
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
abstract class Resque
{
    /**
     * @var string
     */
    const VERSION = '1.2';

    /**#@+
     * Protocol keys
     *
     * @var string
     */
    const QUEUE_KEY   = 'queue:';
    const QUEUES_KEY  = 'queues';
    const WORKERS_KEY = 'workers';
    /**#@-*/

    /**
     * @var Resque\Client
     */
    private $client;

    /**
     * @var array<string => mixed>
     */
    protected $options;

    /**
     * Constructor
     */
    public function __construct($client, array $options = array())
    {
        $this->client = $client;

        $this->configure($options);
    }

    /**
     * Configures the options of the resque background queue system
     *
     * @param array $options
     * @return array<string => mixed>
     */
    public function configure(array $options)
    {
        $this->options = array_merge(array(
            'ps'        => '/bin/ps',
            'ps_args'   => array('-A', '-o', 'pid,command'),
            'grep'      => '/bin/grep',
            'grep_args' => array('[r]esque[^-]')
        ), $options);
    }

    /**
     * Gets the underlying Redis client
     *
     * The Redis client can be any object that implements a suitable subset
     * of Redis commands.
     *
     * @return Resque\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Causes the client to reconnect to the Redis server
     *
     * @return void
     */
    abstract public function reconnect();

    /**
     * Logs a message
     *
     * @param string $message
     * @param string $priority
     * @return void
     */
    abstract public function log($message, $priority = Log::INFO);

    /**
     * Gets a namespaced/prefixed key for the given key suffix
     *
     * @param string $key
     * @return string
     */
    abstract public function getKey($key);

    /**
     * Push a job to the end of a specific queue. If the queue does not
     * exist, then create it as well.
     *
     * @param string $queue The name of the queue to add the job to.
     * @param array $item Job description as an array to be JSON encoded.
     */
    public function push($queue, $item)
    {
        // Add the queue to the list of queues
        $this->getClient()->sadd($this->getKey(self::QUEUES_KEY), $queue);

        // Add the job to the specified queue
        $this->getClient()->rpush($this->getKey(self::QUEUE_KEY . $queue), json_encode($item));
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
        $item = $this->getClient()->lpop($this->getKey(self::QUEUE_KEY . $queue));

        if (!$item) {
            return null;
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
        return $this->getClient()->llen($this->getKey(self::QUEUE_KEY . $queue));
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
            $status = new Status($id, $this);
            $status->create();
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
        $queues = $this->getClient()->smembers($this->getKey(self::QUEUES_KEY));

        if (!is_array($queues)) {
            $queues = array();
        }

        return $queues;
    }

    /**
     * Gets an array of all known worker IDs
     *
     * @return array<string>
     */
    public function getWorkerIds()
    {
        $workers = $this->getClient()->smembers($this->getKey(self::WORKERS_KEY));

        if (!is_array($workers)) {
            $workers = array();
        }

        return $workers;
    }

    /**
     * Return an array of process IDs for all of the Resque workers currently
     * running on this machine.
     *
     * It'd be nice and much easier to use pgrep. It's not available on MacOS.
     *
     * @return array Array of Resque worker process IDs.
     */
    public function getWorkerPids()
    {
        $ps = $this->options['ps'];
        $ps_args = array_map(function ($v) {
            return escapeshellarg($v);
        }, $this->options['ps_args']);

        $grep = $this->options['grep'];
        $grep_args = array_map(function ($v) {
            return escapeshellarg($v);
        }, $this->options['grep_args']);

        $command = sprintf(
            '%s %s | %s %s',
            escapeshellcmd($ps),
            implode($ps_args, ' '),
            escapeshellcmd($grep),
            implode($grep_args, ' ')
        );

        $output = exec($command);

        $pids = array();
        $output = null;
        $return = null;

        exec($command, $output, $return);

        if ($return !== 0) {
            $this->log('Unable to determine worker PIDs');
            return false;
        }

        foreach ($output as $line) {
            $line = explode(' ', trim($line), 2);

            if (!$line[0] || !is_numeric($line[0])) {
                continue;
            }

            $pids[] = (int)$line[0];
        }

        return $pids;
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
