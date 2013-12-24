<?php

namespace Resque\Failure;

use Resque\Exception\Exception;
use Resque\Worker;
use \stdClass;

/**
 * Redis backend for storing failed Resque jobs.
 *
 * @package		Resque/Failure
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
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
        $data->payload   = $payload;
        $data->exception = $this->getClass($exception);
        $data->error     = $this->getErrorMessage($this->getDistalCause($exception));
        $data->worker    = (string)$worker;
        $data->queue     = $queue;
        $data->backtrace = $this->getBacktrace($exception);

        $data = json_encode($data);
        $worker->getResque()->getClient()->rpush($worker->getResque()->getKey('failed'), $data);
    }

    /**
     * Gets the backtrace for the exception
     *
     * The backtrace area is the only part of the failure that's shown on
     * multiple lines by resque-web. So, we'll also use it to mention the
     * wrapping exceptions.
     *
     * @param \Exception $exception
     * @return array
     */
    protected function getBacktrace(\Exception $exception)
    {
        $backtrace = array();

        $backtrace[] = '---';
        $backtrace[] = $this->getErrorMessage($exception);
        $backtrace[] = '---';

        // Allow marshalling of the trace: PHP marks getTraceAsString as final :-(
        if (method_exists($exception, 'getPreviousTraceAsString')) {
            $backtrace = array_merge($backtrace, explode("\n", $exception->getPreviousTraceAsString()));
        } else {
            $backtrace = array_merge($backtrace, explode("\n", $exception->getTraceAsString()));
        }

        if (($previous = $exception->getPrevious())) {
            $backtrace = array_merge($backtrace, $this->getBacktrace($previous)); // Recurse
        }

        return $backtrace;
    }

    /**
     * Find the ultimate cause exception, by following previous members right back
     *
     * @param \Exception|\Resque\Exception\Exception $exception
     * @return Exception
     */
    protected function getDistalCause(\Exception $exception)
    {
        if (($previous = $exception->getPrevious())) {
            return $this->getDistalCause($previous);
        }
        return $exception;
    }

    /**
     * Find the class names of the exceptions
     *
     * @param \Exception|\Resque\Exception\Exception $exception
     * @return string
     */
    protected function getClass(\Exception $exception)
    {
        $message = '';

        if (($previous = $exception->getPrevious())) {
            $message = $this->getClass($previous) . ' < '; // Recurse
        }

        // Let the exception lie about its class: to support marshalling exceptions
        if (method_exists($exception, 'getClass')) {
            $message .= $exception->getClass();
        } else {
            $message .= get_class($exception);
        }

        return $message;
    }

    /**
     * Gets a single string error message from the exception
     *
     * @param \Exception $exception
     * @return string
     */
    protected function getErrorMessage(\Exception $exception)
    {
        return $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine();
    }
}
