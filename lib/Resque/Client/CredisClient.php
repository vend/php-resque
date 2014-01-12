<?php

namespace Resque\Client;

use Credis_Client;

/**
 * Extremely light wrapper around Credis client that allows it to be used with
 * php-resque
 *
 * The main thing that's missing is access to the protected $connected variable.
 *
 * @method bool sismember($key, $value)
 */
class CredisClient extends Credis_Client
{
    public function isConnected()
    {
        return $this->connected;
    }

    public function disconnect()
    {
        $this->close();
    }
}
