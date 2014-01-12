<?php

namespace Resque;

use \Exception;

/**
 * JobInterface
 *
 * Implement this to use a custom class hierarchy; that is, if you don't want
 * to subclass AbstractJob (which is probably much easier)
 */
interface JobInterface
{
    /**
     * Constructor
     *
     * @param string $queue
     * @param array $payload
     */
    public function __construct($queue, array $payload);

    /**
     * Actually performs the work of the job
     *
     * @return void
     */
    public function perform();

    /**
     * @return string
     */
    public function getQueue();

    /**
     * @return array
     */
    public function getPayload();
}
