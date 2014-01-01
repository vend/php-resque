<?php

namespace Resque\Test;

use Resque\Client;

/**
 * Not for use outside of tests
 *
 * Normally, you'd already have a Redis client in your application, and you'd
 * use that. This factory is just to let us test php-resque with different types
 * of client.
 */
class ClientFactory
{
    const ENV = 'PHP_RESQUE_CLIENT_TYPE';
    const DEFAULT_TYPE = 'predis';

    /**
     * @return string
     */
    protected function getType()
    {
        return !empty($_ENV[self::ENV]) ? $_ENV[self::ENV] : self::DEFAULT_TYPE;
    }

    /**
     * @param $type
     * @param string $host
     * @param int $port
     * @param int $db
     * @param string $prefix
     * @return Client
     * @throws \InvalidArgumentException
     */
    public function get($host = 'localhost', $port = 6479, $db = 0, $prefix = '')
    {
        $type = $this->getType();

        switch ($type) {
            case 'predis':
                return new \Predis\Client(array(
                    'host'   => $host,
                    'port'   => $port,
                    'db'     => $db,
                    'prefix' => $prefix
                ));
            case 'credis':
                return new \Credis_Client($host, $port);
            default:
                throw new \InvalidArgumentException('Invalid client type');
        }
    }
}
