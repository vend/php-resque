<?php

namespace Resque;

/**
 * JobInterface
 *
 * Implement this to use a custom class hierarchy; that is, if you don't want
 * to subclass AbstractJob (which is probably much easier)
 */
interface JobInterface
{
    public function __construct($queue, array $payload);

    public function perform();
}
