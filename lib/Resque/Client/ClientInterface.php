<?php

namespace Resque\Client;

/**
 * Client interface
 *
 * This interface represents the capabilities Resque expects from a client
 * object. Your client doesn't have to actually implement this interface: any
 * object that can handle these calls will do fine.
 *
 * This interface is also used to mock out a client for testing.
 *
 * The following clients should be compatible by default:
 *  - Predis\Client
 *
 * Wrappers are provided for the following clients:
 *  - Credis_Client
 */
interface ClientInterface
{
    /**
     * @param string $key
     *
     * @return string
     */
    public function get($key);

    /**
     * @param string $key
     */
    public function del($key);

    /**
     * @param string $key
     * @param string $value
     */
    public function set($key, $value);

    /**
     * @param string $key
     * @param integer $int
     */
    public function incrby($key, $int);

    /**
     * @param string $key
     * @param integer $int
     */
    public function decrby($key, $int);

    /**
     * @param string $key
     *
     * @return string
     */
    public function lpop($key);

    /**
     * @param string $key
     */
    public function llen($key);

    /**
     * @param string $key
     * @param string $value
     */
    public function rpush($key, $value);

    /**
     * @param string $key
     */
    public function smembers($key);

    /**
     * @param string $key
     * @param string $value
     */
    public function sadd($key, $value);

    /**
     * @param string $key
     * @param string $value
     */
    public function sismember($key, $value);

    /**
     * @param string $key
     * @param string $value
     */
    public function srem($key, $value);

    public function hgetall($key);
    public function hmset($key, array $hash);

    public function flushdb($db = null);

    public function isConnected();
    public function disconnect();
    public function connect();

    public function expire($key, $ttl);
}
