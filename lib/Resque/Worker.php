<?php

namespace Resque;

use Resque\Stat;
use Resque\Event;
use Resque\Job;
use Resque\Job\DirtyExitException;

use Predis\Client;

use \VendUtil;

/**
 * Resque worker that handles checking queues for jobs, fetching them
 * off the queues, running them and handling the result.
 *
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
abstract class Worker
{
    /**
     * @var string String identifying this worker.
     */
    protected $id;

    /**
     * @var array Array of all associated queues for this worker.
     */
    protected $queues = array();

    /**
     * @var string The hostname of this worker.
     */
    protected $hostname;

    /**
     * @var boolean True if on the next iteration, the worker should shutdown.
     */
    protected $shutdown = false;

    /**
     * @var boolean True if this worker is paused.
     */
    protected $paused = false;

    /**
     * @var Resque_Job Current job, if any, being processed by this worker.
     */
    protected $currentJob = null;

    /**
     * @var int Process ID of child worker processes.
     */
    private $child = null;

    /**
     * @var Resque
     */
    protected $resque;

    public function log($message, $priority = 'notice')
    {
        $this->resque->log($message, $priority);
    }

    abstract protected function dispatchEvent($event, $job);
    abstract protected function getClient();
    abstract protected function createJobInstance($queue, $payload);

    // @todo
    // abstract protected function getClientRefresh();

    /**
     * Instantiate a new worker, given a list of queues that it should be working
     * on. The list of queues should be supplied in the priority that they should
     * be checked for jobs (first come, first served)
     *
     * Passing a single '*' allows the worker to work on all queues in alphabetical
     * order. You can easily add new queues dynamically and have them worked on using
     * this method.
     *
     * @param string|array $queues String with a single queue name, array with multiple.
     */
    public function __construct(Resque $resque, $queues)
    {
        $this->resque = $resque;

        if (!is_array($queues)) {
            $queues = array($queues);
        }
        $this->queues = $queues;

        if(function_exists('gethostname')) {
            $hostname = gethostname();
        } else {
            $hostname = php_uname('n');
        }
        $this->hostname = $hostname;
        $this->id = $this->hostname . ':'.getmypid() . ':' . implode(',', $this->queues);
    }

    /**
     * Set the ID of this worker to a given ID string.
     *
     * @param string $workerId ID for the worker.
     */
    public function setId($id)
    {
        $this->id = $workerId;
    }

    public function getResque()
    {
        return $this->resque;
    }

    /**
     * Given a worker ID, check if it is registered/valid.
     *
     * @param string $workerId ID of the worker.
     * @return boolean True if the worker exists, false if not.
     */
    public function exists($workerId)
    {
        return (bool)$this->resque->getClient()->sismember('workers', $workerId);
    }

    protected function getStatistic($name)
    {
        return new Statistic($this->resque->getClient(), $name);
    }

    /**
     * The primary loop for a worker which when called on an instance starts
     * the worker's life cycle.
     *
     * Queues are checked every $interval (seconds) for new jobs.
     *
     * @param int $interval How often to check for new jobs across the queues.
     */
    public function work($interval = 5)
    {
        $this->updateProcLine('Starting');
        $this->startup();

        while (true) {
            if ($this->shutdown) {
                break;
            }

            // Attempt to find and reserve a job
            $job = false;
            if (!$this->paused) {
                $job = $this->reserve();
            }

            if (!$job) {
                // For an interval of 0, break now - helps with unit testing etc
                if ($interval == 0) {
                    break;
                }

                // If no job was found, we sleep for $interval before continuing and checking again
                $this->log('Sleeping for ' . $interval, 'info');

                if ($this->paused) {
                    $this->updateProcLine('Paused');
                } else {
                    $this->updateProcLine('Waiting for ' . implode(',', $this->queues));
                }

                usleep($interval * 1000000);
                continue;
            }

            $this->log('got ' . $job);
            $this->dispatchEvent('beforeFork', $job);
            $this->workingOn($job);

            $this->child = $this->fork();

            // Forked and we're the child. Run the job.
            if ($this->child === 0 || $this->child === false) {
                $status = 'Processing ' . $job->queue . ' since ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->log($status, 'verbose');
                $this->perform($job);
                if ($this->child === 0) {
                    exit(0);
                }
            }

            if($this->child > 0) {
                // Parent process, sit and wait
                $status = 'Forked ' . $this->child . ' at ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->log($status, 'verbose');

                // Wait until the child process finishes before continuing
                pcntl_wait($status);
                $exitStatus = pcntl_wexitstatus($status);
                if($exitStatus !== 0) {
                    $job->fail(new Resque_Job_DirtyExitException(
                        'Job exited with exit code ' . $exitStatus
                    ));
                }
            }

            $this->child = null;
            $this->doneWorking();
        }

        $this->unregisterWorker();
    }

    /**
     * Process a single job.
     *
     * @param Resque_Job $job The job to be processed.
     */
    public function perform(Resque_Job $job)
    {
        try {
            $this->dispatchEvent('afterFork', $job);
            $job->perform();
        }
        catch(Exception $e) {
            $this->log($job . ' failed: ' . $e->getMessage());
            $job->fail($e);
            return;
        }

        $job->updateStatus(Resque_Job_Status::STATUS_COMPLETE);
        $this->log('done ' . $job);
    }

    /**
     * Attempt to find a job from the top of one of the queues for this worker.
     *
     * @return object|boolean Instance of Resque_Job if a job is found, false if not.
     */
    public function reserve()
    {
        $queues = $this->queues();

        if (!is_array($queues)) {
            return;
        }

        $job = false;
        foreach($queues as $queue) {
            $this->log('Checking ' . $queue, 'info');

            $payload = $this->resque->pop($queue);
            if (!is_array($payload)) {
                continue;
            }

            $job = $this->createJobInstance($queue, $payload);
            if ($job) {
                $this->log('Found job on ' . $queue, 'info');
                return $job;
            }
        }

        return false;
    }

    /**
     * Return an array containing all of the queues that this worker should use
     * when searching for jobs.
     *
     * If * is found in the list of queues, every queue will be searched in
     * alphabetic order. (@see $fetch)
     *
     * @param boolean $fetch If true, and the queue is set to *, will fetch
     * all queue names from redis.
     * @return array Array of associated queues.
     */
    public function queues($fetch = true)
    {
        if(!in_array('*', $this->queues) || $fetch == false) {
            return $this->queues;
        }

        $queues = Resque::queues();
        sort($queues);
        return $queues;
    }

    /**
     * Attempt to fork a child process from the parent to run a job in.
     *
     * Return values are those of pcntl_fork().
     *
     * @return int -1 if the fork failed, 0 for the forked child, the PID of the child for the parent.
     */
    private function fork()
    {
        if(!function_exists('pcntl_fork')) {
            return false;
        }

        $pid = pcntl_fork();
        if($pid === -1) {
            throw new RuntimeException('Unable to fork child worker.');
        }

        return $pid;
    }

    /**
     * Perform necessary actions to start a worker.
     */
    private function startup()
    {
        $this->registerSigHandlers();
        $this->pruneDeadWorkers();
        $this->dispatchEvent('beforeFirstFork', $this);
        $this->registerWorker();
    }

    /**
     * On supported systems (with the PECL proctitle module installed), update
     * the name of the currently running process to indicate the current state
     * of a worker.
     *
     * @param string $status The updated process title.
     */
    protected function updateProcLine($status)
    {
        if (function_exists('setproctitle')) {
            setproctitle('resque-' . Resque::VERSION . ': ' . $status);
        }
    }

    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM: Shutdown immediately and stop processing jobs.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     */
    private function registerSigHandlers()
    {
        if(!function_exists('pcntl_signal')) {
            return;
        }

        declare(ticks = 1);
        pcntl_signal(SIGTERM, array($this, 'shutDownNow'));
        pcntl_signal(SIGINT, array($this, 'shutDownNow'));
        pcntl_signal(SIGQUIT, array($this, 'shutdown'));
        pcntl_signal(SIGUSR1, array($this, 'killChild'));
        pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
        pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));
        pcntl_signal(SIGPIPE, array($this, 'reestablishRedisConnection'));
        $this->log('Registered signals', 'verbose');
    }

    /**
     * Signal handler callback for USR2, pauses processing of new jobs.
     */
    public function pauseProcessing()
    {
        $this->log('USR2 received; pausing job processing');
        $this->paused = true;
    }

    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     */
    public function unPauseProcessing()
    {
        $this->log('CONT received; resuming job processing');
        $this->paused = false;
    }

    /**
     * Signal handler for SIGPIPE, in the event the redis connection has gone away.
     * Attempts to reconnect to redis, or raises an Exception.
     */
    public function reestablishRedisConnection()
    {
        $this->log('SIGPIPE received; attempting to reconnect');
        $this->resque->getClient()->establishConnection();
    }

    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     */
    public function shutdown()
    {
        $this->shutdown = true;
        $this->log('Exiting...');
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently running.
     */
    public function shutdownNow()
    {
        $this->shutdown();
        $this->killChild();
    }

    /**
     * Kill a forked child job immediately. The job it is processing will not
     * be completed.
     */
    public function killChild()
    {
        if(!$this->child) {
            $this->log('No child to kill.', self::LOG_VERBOSE);
            return;
        }

        $this->log('Killing child at ' . $this->child, self::LOG_VERBOSE);
        if(exec('ps -o pid,state -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
            $this->log('Killing child at ' . $this->child, self::LOG_VERBOSE);
            posix_kill($this->child, SIGKILL);
            $this->child = null;
        }
        else {
            $this->log('Child ' . $this->child . ' not found, restarting.', self::LOG_VERBOSE);
            $this->shutdown();
        }
    }

    /**
     * Look for any workers which should be running on this server and if
     * they're not, remove them from Redis.
     *
     * This is a form of garbage collection to handle cases where the
     * server may have been killed and the Resque workers did not die gracefully
     * and therefore leave state information in Redis.
     *
     * @deprecated
     */
    public function pruneDeadWorkers()
    {
        VendUtil::deprecated();
    }

    /**
     * Return an array of process IDs for all of the Resque workers currently
     * running on this machine.
     *
     * @return array Array of Resque worker process IDs.
     * @deprecated
     */
    public function workerPids()
    {
        VendUtil::deprecated();
    }

    /**
     * Register this worker in Redis.
     */
    public function registerWorker()
    {
        $this->resque->getClient()->sadd('workers', $this);
        $this->resque->getClient()->set('worker:' . (string)$this . ':started', strftime('%a %b %d %H:%M:%S %Z %Y'));
    }

    /**
     * Unregister this worker in Redis. (shutdown etc)
     */
    public function unregisterWorker()
    {
        if(is_object($this->currentJob)) {
            $this->currentJob->fail(new Resque_Job_DirtyExitException);
        }

        $id = (string)$this;
        $this->resque->getClient()->srem('workers', $id);
        $this->resque->getClient()->del('worker:' . $id);
        $this->resque->getClient()->del('worker:' . $id . ':started');
        $this->getStatistic('processed:' . $id)->clear();
        $this->getStatistic('failed:' . $id)->clear();
    }

    /**
     * Tell Redis which job we're currently working on.
     *
     * @param object $job Resque_Job instance containing the job we're working on.
     */
    public function workingOn(Resque_Job $job)
    {
        $job->worker = $this;
        $this->currentJob = $job;
        $job->updateStatus(Resque_Job_Status::STATUS_RUNNING);
        $data = json_encode(array(
            'queue' => $job->queue,
            'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
            'payload' => $job->payload
        ));
        $this->resque->getClient()->set('worker:' . $job->worker, $data);
    }

    /**
     * Notify Redis that we've finished working on a job, clearing the working
     * state and incrementing the job stats.
     */
    public function doneWorking()
    {
        $this->currentJob = null;
        $this->getStatistic()->incr('processed');
        $this->getStatistic()->incr('processed:' . (string)$this);
        $this->resque->getClient()->del('worker:' . (string)$this);
    }

    /**
     * Generate a string representation of this worker.
     *
     * @return string String identifier for this worker instance.
     */
    public function __toString()
    {
        return $this->id;
    }

    /**
     * Return an object describing the job this worker is currently working on.
     *
     * @return object Object with details of current job.
     */
    public function job()
    {
        $job = $this->resque->getClient()->get('worker:' . $this);
        if (!$job) {
            return array();
        }
        else {
            return json_decode($job, true);
        }
    }

    /**
     * Get a statistic belonging to this worker.
     *
     * @param string $stat Statistic to fetch.
     * @return int Statistic value.
     */
    public function getStat($stat)
    {
        return $this->getStatistic()->get($stat . ':' . $this);
    }
}
