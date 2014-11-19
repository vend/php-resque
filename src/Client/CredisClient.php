<?php

namespace Resque\Client;

use Credis_Client;

/**
 * Very light wrapper around Credis client that allows it to be used with php-resque
 *
 * @method bool sismember($key, $value)
 */
class CredisClient extends Credis_Client
{
    /**
     * Whether the client is connected to the server
     *
     * Overridden to provide access to the protected $connected variable.
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->connected;
    }

    /**
     * Disconnects the client
     */
    public function disconnect()
    {
        $this->close();
    }

    /**
     * Alias to exec() for pipeline compatibility with Predis
     */
    public function execute()
    {
        $this->__call('exec', array());
    }
}
