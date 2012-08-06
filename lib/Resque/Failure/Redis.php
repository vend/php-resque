<?php

namespace Resque\Failure;

use Resque\Failure\FailureBackend;
use \stdClass;

/**
 * Redis backend for storing failed Resque jobs.
 *
 * @package        Resque/Failure
 * @author        Chris Boulton <chris.boulton@interspire.com>
 * @copyright    (c) 2010 Chris Boulton
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class Redis implements FailureBackend
{
    /**
     * Initialize a failed job class and save it (where appropriate).
     *
     * @param object $payload Object containing details of the failed job.
     * @param Exception $exception Instance of the exception that was thrown by the failed job.
     * @param Worker $worker Instance of Worker that received the job.
     * @param string $queue The name of the queue the job was fetched from.
     */
    public function __construct($payload, $exception, $worker, $queue)
    {
        $data = new stdClass;
        $data->failed_at = strftime('%a %b %d %H:%M:%S %Z %Y');
        $data->payload = $payload;

        // Let the exception lie about its class: to support marshalling exceptions
        if (method_exists($exception, 'getClass')) {
            $data->exception = $exception->getClass();
        } else {
            $data->exception = get_class($exception);
        }

        // Allow marshalling of the trace: PHP marks getTraceAsString as final :-(
        if (method_exists($exception, 'getPreviousTraceAsString')) {
            $data->backtrace = explode("\n", $exception->getPreviousTraceAsString());
        } else {
            $data->backtrace = explode("\n", $exception->getTraceAsString());
        }

        $data->error = $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine();
        $data->worker = (string)$worker;
        $data->queue = $queue;

        $data = json_encode($data);
        $worker->getResque()->getClient()->rpush($worker->getResque()->getKey('failed'), $data);
    }
}
