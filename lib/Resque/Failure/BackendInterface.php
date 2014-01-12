<?php

namespace Resque\Failure;

use Exception;
use Resque\Worker;

/**
 * Interface that all failure backends should implement
 *
 * @package		Resque/Failure
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
interface BackendInterface
{
	/**
	 * Initialize a failed job class and save it (where appropriate).
	 *
	 * @param array $payload Object containing details of the failed job.
	 * @param \Exception $exception Instance of the exception that was thrown by the failed job.
	 * @param Worker $worker Instance of Worker that received the job.
	 * @param string $queue The name of the queue the job was fetched from.
	 */
	public function receiveFailure($payload, Exception $exception, Worker $worker, $queue);
}
