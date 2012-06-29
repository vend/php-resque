<?php

namespace Resque\Job;

use Resque\Resque;
use \InvalidArgumentException;

/**
 * Status tracker/information for a job.
 *
 * @author		Chris Boulton <chris.boulton@interspire.com>
 * @copyright	(c) 2010 Chris Boulton
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Status
{
    /**
     * How many seconds until a complete status entry should expire
     *
     * @var int
     */
    const COMPLETE_TTL = 86400;

    /**#@+
     * Status codes
     *
     * @var int
     */
	const STATUS_WAITING = 1;
	const STATUS_RUNNING = 2;
	const STATUS_FAILED = 3;
	const STATUS_COMPLETE = 4;
	/**#@-*/

    /**
     * An array of valid statuses
     *
     * @var array<int>
     */
    protected static $valid = array(
        self::STATUS_WAITING,
        self::STATUS_RUNNING,
        self::STATUS_FAILED,
        self::STATUS_COMPLETE
    );

    /**
     * An array of complete statuses
     *
     * @var array<int>
     */
    protected static $complete = array(
        self::STATUS_FAILED,
        self::STATUS_COMPLETE
    );

	/**
	 * @var string The ID of the job this status class refers back to.
	 */
	protected $id;

	/**
	 * @var Resque
	 */
	protected $resque;

	/**
	 * @var boolean|null  Cache variable if the status of this job is being
     *                     monitored or not. True/false when checked at least
     *                     once or null if not checked yet.
	 */
	protected $isTracking = null;

	/**
	 * Setup a new instance of the job monitor class for the supplied job ID.
	 *
	 * @param string $id The ID of the job to manage the status for.
	 */
	public function __construct($id, Resque $resque)
	{
		$this->id = $id;
		$this->resque = $resque;
	}

	/**
	 * Create a new status monitor item for the supplied job ID. Will create
	 * all necessary keys in Redis to monitor the status of a job.
	 *
	 * @param string $id The ID of the job to monitor the status of.
	 */
	public function create()
	{
        if ($this->resque->getClient()->set((string)$this, $this->getStatusPayload(self::STATUS_WAITING))) {
            $this->isTracking = true;
        }
	}

    /**
     * Gets a status payload
     *
     * @param int $status
     * @return array
     */
    protected function getStatusPayload($status)
    {
        if (!in_array($status, self::$valid)) {
            throw new InvalidArgumentException('Invalid status');
        }

        $payload = array(
            'status'  => $status,
            'updated' => time()
        );

        if ($status == self::STATUS_WAITING) {
            $payload['started'] = time();
        }

        return json_encode($payload);
    }

	/**
	 * Check if we're actually checking the status of the loaded job status
	 * instance.
	 *
	 * @return boolean True if the status is being monitored, false if not.
	 */
	public function isTracking()
	{
        if ($this->isTracking === null) {
            $this->isTracking = (boolean)$this->resque->getClient()->exists((string)$this);
        }

        return $this->isTracking;
	}

	/**
	 * Update the status indicator for the current job with a new status.
	 *
	 * @param int The status of the job (see constants in Resque\Job\Status)
	 */
	public function update($status)
	{
		if (!$this->isTracking()) {
			return;
		}

		$this->resque->getClient()->set((string)$this, $this->getStatusPayload($status));

		// Expire the status for completed jobs after 24 hours
		if (in_array($status, self::$complete)) {
			$this->resque->getClient()->expire((string)$this, self::COMPLETE_TTL);
		}
	}

	/**
	 * Fetch the status for the job being monitored.
	 *
	 * @return mixed False if the status is not being monitored, otherwise the status as
	 * 	as an integer, based on the Resque\Job\Status constants.
	 */
	public function get()
	{
		if (!$this->isTracking()) {
			return false;
		}

        $status = $this->resque->getClient()->get((string)$this);

        if (!$status) {
            return false;
        }

        $statusPacket = json_decode($status, true);

		if (!$statusPacket) {
			return false;
		}

		return $statusPacket['status'];
	}

	/**
	 * Stop tracking the status of a job.
	 *
	 * @return void
	 */
	public function stop()
	{
		$this->resque->getClient()->del((string)$this);
	}

	/**
	 * Generate a string representation of this object.
	 *
	 * @return string String representation of the current job status class.
	 */
	public function __toString()
	{
		return 'job:' . $this->id . ':status';
	}
}
