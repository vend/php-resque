<?php

namespace Resque\Exception;

use Resque\ResqueException;

/**
 * Exception class for a job that does not exit cleanly
 *
 * @package        Resque/Job
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class DirtyExitException extends ResqueException
{
}
