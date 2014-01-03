<?php


namespace Resque;

use Psr\Log\LoggerAwareInterface;
use Resque\Event;
use Resque\Exception\Exception;
use Resque\Job;
use Resque\Job\DirtyExitException;
use Resque\Job\Status;
use Resque\Util\Configurable;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use \RuntimeException;


/**
 * Resque worker that handles checking queues for jobs, fetching them
 * off the queues, running them and handling the result.
 *
 * @package		Resque/Worker
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Worker implements LoggerAwareInterface
{
    /**
     * @var string String identifying this worker.
     */
    protected $id;

    /**
     * @var LoggerInterface Logging object that implements the PSR-3 LoggerInterface
     */
    protected $logger;

    /**
     * @var array Array of all associated queues for this worker.
     */
    protected $queues = array();

    /**
     * @var boolean True if on the next iteration, the worker should shutdown.
     */
    protected $shutdown = false;

    /**
     * @var boolean True if this worker is paused.
     */
    protected $paused = false;

    /**
     * @var Job Current job, if any, being processed by this worker.
     */
    protected $currentJob = null;

    protected $options = array();

    /**
     * @var int Process ID of child worker processes.
     */
    private $child = null;

    /**
     * @var Resque
     */
    protected $resque;

    protected function getClient()
    {
        return $this->resque->getClient();
    }

    /**
     * @param $queue
     * @param $payload
     * @throws \LogicException
     * @todo Get job name from payload
     */
    protected function createJobInstance($queue, $payload)
    {
        throw new \LogicException('unimplemented');
    }

    /**
     * Instantiate a new worker, given a list of queues that it should be working
     * on. The list of queues should be supplied in the priority that they should
     * be checked for jobs (first come, first served)
     *
     * Passing a single '*' allows the worker to work on all queues in alphabetical
     * order. You can easily add new queues dynamically and have them worked on using
     * this method.
     *
     * @param Resque $resque
     * @param string|array $queues String with a single queue name, array with multiple.
     * @param array $options
     */
    public function __construct(Resque $resque, $queues, array $options = array())
    {
        $this->configure($options);

        $this->resque = $resque;

        if (!is_array($queues)) {
            $queues = array($queues);
        }
        $this->queues = $queues;

        $this->configureId();
    }

    /**
     * Configures options for the worker
     *
     * @param array $options
     *   Including
     *     Worker identification
     *       - server_name      => string, default is FQDN hostname
     *       - pid              => int, default is current PID
     *       - id_format        => string, suitable for sprintf
     *       - id_location_preg => string, Perl compat regex, gets hostname and PID
     *                               out of worker ID
     *
     * @see Resque\Util.Configurable::configure()
     */
    protected function configure(array $options)
    {
        $this->options = array_merge(array(
            'server_name'      => null,
            'pid'              => null,
            'id_format'        => '%s:%d:%s',
            'id_location_preg' => '/^([^:]+?):([0-9]+):/',
        ), $this->options);

        if (!$this->options['server_name']) {
            $this->options['server_name'] = function_exists('gethostname') ? gethostname() : php_uname('n');
        }

        if (!$this->options['pid']) {
            $this->options['pid'] = getmypid();
        }
    }

    /**
     * Configures the ID of this worker
     */
    protected function configureId()
    {
        $this->id = sprintf(
            $this->options['id_format'],
            $this->options['server_name'],
            $this->options['pid'],
            implode(',', $this->queues)
        );
    }

    /**
     * Parses a hostname and PID out of a string worker ID
     *
     * If you change the format of the ID, you should also change the definition
     * of this method.
     *
     * This method *always* parses the ID of the worker, rather than figuring out
     * the current processes' PID/hostname. This means you can use setId() to
     * interrogate the properties of other workers given their ID.
     *
     * @throws Exception
     * @return array(string hostname, int pid)
     */
    protected function getLocation()
    {
        $matches = array();

        if (!preg_match($this->options['id_location_preg'], $this->getId(), $matches)) {
            throw new Exception('Incompatible ID format: unable to determine worker location');
        }

        if (!isset($matches[1]) || !$matches[1]) {
            throw new Exception('Invalid ID: invalid hostname');
        }

        if (!isset($matches[2]) || !$matches[2] || !is_numeric($matches[2])) {
            throw new Exception('Invalid ID: invalid PID');
        }

        return array($matches[1], (int)$matches[2]);
    }

    /**
     * Set the ID of this worker to a given ID string.
     *
     * @param string $id ID for the worker.
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Gives access to the main Resque system this worker belongs to
     *
     * @return \Resque\Resque
     */
    public function getResque()
    {
        return $this->resque;
    }

    /**
     * Logs a message
     *
     * @param string $message
     * @param string $priority
     */
    public function log($message, $priority = LogLevel::INFO)
    {
        $this->logger->log($message, $priority);
    }

    /**
     * Given a worker ID, check if it is registered/valid.
     *
     * @param string $id ID of the worker.
     * @return boolean True if the worker exists, false if not.
     */
    public function exists($id)
    {
        return (bool)$this->resque->getClient()->sismember('workers', $id);
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
                if ($this->paused) {
                    $this->updateProcLine('Paused');
                } else {
                    $this->updateProcLine('Waiting for ' . implode(',', $this->queues));
                }

                usleep($interval * 1000000);
                continue;
            }

            $this->log('got ' . $job);
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
                    $job->fail(new DirtyExitException(
                        'Job exited with exit code ' . $exitStatus
                    ));
                }
            }

            $this->child = null;
            $this->doneWorking();
        }

        $this->unregister();
    }

    /**
     * Process a single job.
     *
     * @param Job $job The job to be processed.
     */
    public function perform(Job $job)
    {
        try {
            $job->perform();
        }
        catch(Exception $e) {
            $this->log($job . ' failed: ' . $e->getMessage());
            $job->fail($e);
            return;
        }

        $this->log('Done ' . $job, 'debug');
    }

    /**
     * Attempt to find a job from the top of one of the queues for this worker.
     *
     * @return object|boolean Instance of Job if a job is found, false if not.
     */
    public function reserve()
    {
        $queues = $this->queues();

        if (!is_array($queues)) {
            return false;
        }

        // Each call to reserve, we check the queues in a different order
        shuffle($queues);

        $job = false;
        foreach($queues as $queue) {
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

        $queues = $this->resque->queues();
        sort($queues);
        return $queues;
    }

    /**
     * Attempt to fork a child process from the parent to run a job in.
     *
     * Return values are those of pcntl_fork().
     *
     * @throws \RuntimeException
     * @throws \Exception
     * @return int|false -1 if the fork failed, 0 for the forked child, the PID of the child for the parent.
     */
    private function fork()
    {
        if (!function_exists('pcntl_fork')) {
            throw new \Exception('pcntl not available, could not fork');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork child worker.');
        }

        return $pid;
    }

    /**
     * Perform necessary actions to start a worker.
     */
    protected function startup()
    {
        $this->registerSigHandlers();
        $this->pruneDeadWorkers();
        $this->register();
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
     * INT:  Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     */
    protected function registerSigHandlers()
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
     */
    public function pruneDeadWorkers()
    {
        $pids = $this->resque->getWorkerPids();
        $ids  = $this->resque->getWorkerIds();

        foreach ($ids as $id) {
            $worker = clone $this;
            $worker->setId($id);

            list($host, $pid) = $worker->getLocation();

            // Ignore workers on other hosts
            if ($host != $this->options['server_name']) {
                continue;
            }

            // Ignore this process
            if ($pid == $this->options['pid']) {
                continue;
            }

            // Ignore workers still running
            if (in_array($pid, $pids)) {
                continue;
            }

            $this->log('Pruning dead worker: ' . $id, LogLevel::WARNING);
            $worker->unregister();
        }
    }

    /**
     * Gets the ID of this worker
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Register this worker in Redis.
     */
    public function register()
    {
        $this->resque->getClient()->sadd($this->resque->getKey(Resque::WORKERS_KEY), $this->getId());
        $this->resque->getClient()->set($this->getJobKey() . ':started', strftime('%a %b %d %H:%M:%S %Z %Y'));
    }

    /**
     * Unregister this worker in Redis. (shutdown etc)
     */
    public function unregister()
    {
        if (is_object($this->currentJob)) {
            $this->currentJob->fail(new DirtyExitException());
        }

        $this->resque->getClient()->srem($this->resque->getKey(Resque::WORKERS_KEY), $this->getId());
        $this->resque->getClient()->del($this->getJobKey());
        $this->resque->getClient()->del($this->getJobKey() . ':started');

        $this->getStatistic('processed')->clear();
        $this->getStatistic('failed')->clear();
        $this->getStatistic('shutdown')->clear();
    }

    /**
     * Tell Redis which job we're currently working on.
     *
     * @param \Resque\Job $job Job instance containing the job we're working on.
     */
    public function workingOn(Job $job)
    {
        $this->currentJob = $job;

        $job->worker = $this;
        $job->updateStatus(Status::STATUS_RUNNING);

        $data = json_encode(array(
            'queue' => $job->queue,
            'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
            'payload' => $job->payload
        ));

        $this->resque->getClient()->set($this->getJobKey(), $data);
    }

    /**
     * Notify Redis that we've finished working on a job, clearing the working
     * state and incrementing the job stats.
     */
    public function doneWorking()
    {
        $this->currentJob = null;
        $this->resque->getStatistic('processed')->incr();
        $this->getStatistic('processed')->incr();
        $this->resque->getClient()->del($this->getJobKey());
    }

    /**
     * Generate a string representation of this worker.
     *
     * @return string String identifier for this worker instance.
     * @deprecated Just use getId(). Explicit, simpler, less magic.
     */
    public function __toString()
    {
        return $this->id;
    }

    /**
     * Gets the key for where this worker will store its active job
     *
     * @return string
     */
    protected function getJobKey()
    {
        return $this->getResque()->getKey('worker:' . $this->getId());
    }

    /**
     * Return an object describing the job this worker is currently working on.
     *
     * @return object Object with details of current job.
     */
    public function job()
    {
        $job = $this->resque->getClient()->get($this->getJobKey());

        if (!$job) {
            return array();
        } else {
            return json_decode($job, true);
        }
    }

    /**
     * Get a statistic belonging to this worker.
     *
     * @param string $name Statistic to fetch.
     * @return int Statistic value.
     */
    public function getStatistic($name)
    {
        return new Statistic($this->resque, $name. ':' . $this->getId());
    }

    /**
     * Inject the logging object into the worker
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
