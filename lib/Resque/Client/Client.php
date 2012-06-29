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
 * The following clients should be compatible:
 *  - Predis\Client
 *  - Redisent
 */
interface Client
{
    public function set($key, $value);
}