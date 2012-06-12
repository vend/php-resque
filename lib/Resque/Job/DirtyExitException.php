<?php

namespace Resque\Job;

use \RuntimeException;

/**
 * Runtime exception class for a job that does not exit cleanly.
 *
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class DirtyExitException extends RuntimeException
{
}