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
     * @return self
     */
    public function pipeline();

    /**
     * @return boolean|\Predis\Response\ResponseInterface
     */
    public function execute();

    /**
     * @return boolean
     */
    public function isConnected();

    /**
     * @param integer $db
     */
    public function flushdb($db = null);

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
     * @param string $key
     * @return bool
     */
    public function exists($key);

    /**
     * @param string  $key
     * @param integer $ttl
     */
    public function expire($key, $ttl);

    /**
     * @param string  $key
     * @param integer $increment
     */
    public function incrby($key, $increment);

    /**
     * @param string  $key
     * @param integer $decrement
     */
    public function decrby($key, $decrement);

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
     * @param string $field
     * @param string $value
     */
    public function hset($key, $field, $value);

    /**
     * @param string $key
     * @return array<string,mixed>
     */
    public function hgetall($key);

    /**
     * @param string $key
     * @param array  $hash
     * @return boolean|\Predis\Response\ResponseInterface
     */
    public function hmset($key, array $hash);

    /**
     * @param string $key
     * @param string $field
     * @param integer $increment
     * @return integer The value at field after the increment operation
     */
    public function hincrby($key, $field, $increment);
}
