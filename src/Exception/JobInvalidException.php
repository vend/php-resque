<?php

namespace Resque\Exception;

use Resque\ResqueException;

/**
 * Generally means the job class itself is invalid. Usually, because it does
 * not implement JobInterface
 */
class JobInvalidException extends ResqueException
{
}
