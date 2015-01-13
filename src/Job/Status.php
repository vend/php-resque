<?php

namespace Resque\Job;

use LogicException;
use Resque\Resque;
use \InvalidArgumentException;

/**
 * Status tracker/information for a job
 *
 * Modified from the original Resque\Status tracker to use a hash; preventing
 * race conditions in updating a single property of the status.
 *
 * @author    Dominic Scheirlinck <dominic@vendhq.com>
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
    public static $valid = array(
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
    public static $complete = array(
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
     * @var array<string,mixed>
     */
    protected $attributes = array();

    /**
     * @var \Resque\Client\ClientInterface
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
     * @param \Resque\Resque $resque
     */
    public function __construct($id, Resque $resque)
    {
        $this->id = $id;
        $this->client = $resque->getClient();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Create a new status monitor item for the supplied job ID. Will create
     * all necessary keys in Redis to monitor the status of a job.
     */
    public function create()
    {
        $this->isTracking = true;

        $this->setAttributes(array(
            'status'  => self::STATUS_WAITING,
            'created' => time(),
            'updated' => time()
        ));
    }

    /**
     * Sets all the given attributes
     *
     * @param array<string,mixed> $attributes
     * @return mixed
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        $set = array();
        foreach ($attributes as $name => $value) {
            if ($name == 'status') {
                $this->update($value);
                continue;
            }
            $set[$name] = $value;
        }

        return call_user_func(array($this->client, 'hmset'), $this->getHashKey(), $set);
    }

    /**
     * Sets an attribute
     *
     * @param string $name
     * @param string  $value
     */
    public function setAttribute($name, $value)
    {
        if ($name == 'status') {
            $this->update($value);
        } else {
            $this->attributes[$name] = $value;
            $this->client->hmset($this->getHashKey(), array(
                $name     => $value,
                'updated' => time()
            ));
        }
    }

    /**
     * Increments an attribute
     *
     * The attribute should be an integer field
     *
     * @param string  $name
     * @param integer $by
     * @return integer The value after incrementing (see hincrby)
     */
    public function incrementAttribute($name, $by = 1)
    {
        $pipeline = $this->client->pipeline();
        $pipeline->hincrby($this->getHashKey(), $name, $by);
        $pipeline->hset($this->getHashKey(), 'updated', time());
        $result = $pipeline->execute();

        return $this->attributes[$name] = $result[0];
    }

    /**
     * Update the status indicator for the current job with a new status.
     *
     * This method is called from setAttribute/s so that the expiry can be
     * properly updated.
     *
     * @param int $status The status of the job (see constants in Resque\Job\Status)
     * @throws \InvalidArgumentException
     * @return boolean
     */
    public function update($status)
    {
        if (!isset(self::$valid[$status])) {
            throw new InvalidArgumentException('Invalid status');
        }

        if (!$this->isTracking()) {
            return false;
        }

        $this->attributes['status'] = $status;
        $this->attributes['updated'] = time();

        $success = $this->client->hmset($this->getHashKey(), array(
            'status'  => $this->attributes['status'],
            'updated' => $this->attributes['updated']
        ));

        // Expire the status for completed jobs after 24 hours
        if (in_array($status, self::$complete)) {
            $this->client->expire($this->getHashKey(), self::COMPLETE_TTL);
        } else {
            $this->client->expire($this->getHashKey(), self::INCOMPLETE_TTL);
        }

        return (boolean)$success;
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
            throw new \LogicException('The status is already loaded. Use another instance.');
        }

        $this->attributes = array_merge($this->attributes, $this->client->hgetall($this->getHashKey()));
        $this->loaded     = true;
    }

    /**
     * @return array<string,mixed>
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
     * @return string
     */
    public function getStatus()
    {
        $status = $this->get();

        if (isset(self::$valid[$status])) {
            return self::$valid[$status];
        }

        return 'unknown';
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

        // Could be just hget, but Credis will return false?!
        $attributes = $this->client->hGetAll($this->getHashKey());
        return isset($attributes[$name]) ? $attributes[$name] : $default;
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

    /**
     * Accessor to return valid statuses
     *
     * @return array<int>
     */
    public function getValid()
    {
        return self::$valid;
    }

    /**
     * Accessor to return complete statuses
     *
     * @return array<int>
     */
    public function getComplete()
    {
        return self::$complete;
    }

    /**
     * Convenience method to to check if a resque job has a complete status
     */
    public function isComplete()
    {
        return in_array($this->get(), self::$complete);
    }
}
