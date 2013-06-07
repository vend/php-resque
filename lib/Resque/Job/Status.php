<?php

namespace Resque\Job;

use Resque\Resque;
use \InvalidArgumentException;

/**
 * Status tracker/information for a job
 *
 * Modified from the original Resque\Status tracker to use a hash; preventing
 * race conditions in updating a single property of the status.
 *
 * @author    Dominic Scheirlinck <dominic@varspool.com>
 * @author    Chris Boulton <chris.boulton@interspire.com>
 * @copyright (c) 2010 Chris Boulton
 * @license   http://www.opensource.org/licenses/mit-license.php
 */
class Status
{
    /**#@+
     * How many seconds until a status entry should expire
     *
     * In the previous implementation, incomplete statuses were not given a
     * TTL at all. This can make Redis treat the keys differently, depending
     * on your maxmemory-policy (for example, volative-lru will only remove
     * keys with an expire set).
     *
     * @var int
     */
    const COMPLETE_TTL   = 86400;    // 24 hours
    const INCOMPLETE_TTL = 604800;   // A week
    /**#@-*/

    /**#@+
     * Status codes
     *
     * @var int
     */
    const STATUS_WAITING   = 1;
    const STATUS_RUNNING   = 2;
    const STATUS_FAILED    = 3;
    const STATUS_COMPLETE  = 4;
    const STATUS_RECREATED = 5;
    /**#@-*/

    /**
     * An array of valid statuses
     *
     * @var array<int>
     */
    protected static $valid = array(
        self::STATUS_WAITING   => 'waiting',
        self::STATUS_RUNNING   => 'running',
        self::STATUS_FAILED    => 'failed',
        self::STATUS_COMPLETE  => 'complete',
        self::STATUS_RECREATED => 'recreated'
    );

    /**
     * An array of complete statuses
     *
     * @var array<int>
     */
    protected static $complete = array(
        self::STATUS_FAILED,
        self::STATUS_COMPLETE,
        self::STATUS_RECREATED
    );

    /**
     * @var string The ID of the job this status class refers back to.
     */
    protected $id;

    /**
     * Whether the status has been loaded from the database
     *
     * @var boolean
     */
    protected $loaded = false;

    /**
     * @var array<string => mixed>
     */
    protected $attributes = array();

    /**
     * @var Predis\Client
     */
    protected $client;

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
        $this->client = $resque->getClient();
    }

    /**
     * Create a new status monitor item for the supplied job ID. Will create
     * all necessary keys in Redis to monitor the status of a job.
     *
     * @param string $id The ID of the job to monitor the status of.
     */
    public function create()
    {
        $this->isTracking = true;

        $this->setAttributes(array(
            'status'  => self::STATUS_WAITING,
            'started' => time(),
            'updated' => time()
        ));
    }

    /**
     * Sets all the given attributes
     *
     * @param array<string => mixed> $attributes
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        $args = array($this->getHashKey());

        foreach ($attributes as $name => $value) {
            if ($name == 'status') {
                $this->update($value);
                continue;
            }
            $args[] = $name;
            $args[] = $value;
        }

        call_user_func_array(array($this->client, 'hmset'), $args);
    }

    /**
     * Sets an attribute
     *
     * @param string $name
     * @param mixed $value
     */
    public function setAttribute($name, $value)
    {
        if ($name == 'status') {
            $this->update($value);
        } else {
            $this->attribute[$name] = $value;
            $this->client->hmset($this->getHashKey(), $name, $value, 'updated', time());
        }
    }

    /**
     * Update the status indicator for the current job with a new status.
     *
     * This method is called from setAttribute/s so that the expiry can be
     * properly updated.
     *
     * @param int The status of the job (see constants in Resque\Job\Status)
     * @return void
     */
    public function update($status)
    {
        if (!isset(self::$valid[$status])) {
            throw new InvalidArgumentException('Invalid status');
        }

        if (!$this->isTracking()) {
            return;
        }

        $this->attribute['status'] = $status;
        $this->client->hmset($this->getHashKey(), 'status', $status, 'updated', time());

        // Expire the status for completed jobs after 24 hours
        if (in_array($status, self::$complete)) {
            $this->client->expire($this->getHashKey(), self::COMPLETE_TTL);
        } else {
            $this->client->expire($this->getHashKey(), self::INCOMPLETE_TTL);
        }
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
            $this->isTracking = (boolean)$this->client->exists($this->getHashKey());
            if ($this->isTracking) {
                $this->load();
            }
        }
        return $this->isTracking;
    }

    /**
     * Loads all status attributes
     *
     * @throws LogicException
     */
    public function load()
    {
        if ($this->loaded) {
            throw new LogicException('The status is already loaded. Use another instance.');
        }

        $this->attributes = array_merge($this->attributes, $this->client->hgetall($this->getHashKey()));
        $this->loaded     = true;
    }

    /**
     * @return array<string => mixed>
     */
    public function getAll()
    {
        if ($this->loaded) {
            return $this->attributes;
        }

        return $this->client->hgetall($this->getHashKey());
    }

    /**
     * Gets the time this status was updated
     */
    public function getUpdated()
    {
        return $this->getAttribute('updated');
    }

    /**
     * Gets the time this status was created
     */
    public function getCreated()
    {
        return $this->getAttribute('created');
    }

    /**
     * Fetch the status for the job being monitored.
     *
     * For consistency, this would be called getStatus(), but for BC, it's
     * just get().
     *
     * @return null|integer
     */
    public function get()
    {
        return $this->getAttribute('status');
    }

    /**
     * Gets a single attribute value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute($name, $default = null)
    {
        if ($this->loaded) {
            return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
        }

        $value = $this->client->hget($this->getHashKey(), $name);
        return $value !== null ? $value : $default;
    }

    /**
     * Stop tracking the status of a job.
     *
     * @return void
     */
    public function stop()
    {
        $this->client->del($this->getHashKey());
    }

    /**
     * A new key, because we're now using a hash format to store the status
     *
     * Used from outside this class to do status processing more efficiently
     *
     * @return string
     */
    public function getHashKey()
    {
        return 'job:' . $this->id . ':status/hash';
    }
}
