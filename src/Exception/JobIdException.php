<?php

namespace Resque\Exception;

use Resque\ResqueException;

/**
 * Usually because a job on the queue doesn't have an ID field in its payload
 */
class JobIdException extends ResqueException
{
}
