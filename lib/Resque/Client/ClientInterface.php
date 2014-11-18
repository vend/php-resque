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
    public function connect();
    public function disconnect();

    /**
     * @return boolean
     */
    public function isConnected();

    /**
     * @param string $key
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
     * @param string  $key
     * @param integer $int
     */
    public function incrby($key, $int);

    /**
     * @param string  $key
     * @param integer $int
     */
    public function decrby($key, $int);

    /**
     * @param string $key
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
     * @return array
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

    /**
     * @param string $key
     * @return array
     */
    public function hgetall($key);

    /**
     * @param string $key
     * @param array  $hash
     * @return boolean|\Predis\Response\ResponseInterface
     */
    public function hmset($key, array $hash);

    /**
     * @param int $db
     */
    public function flushdb($db = null);

    /**
     * @param string $key
     * @param int    $ttl
     */
    public function expire($key, $ttl);
}
