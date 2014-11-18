<?php

namespace Resque\Console;

use Psr\Log\LoggerInterface;
use Resque\Client\ClientInterface;
use Symfony\Component\Console\Helper\Helper;

/**
 * Logger CLI helper
 */
class LoggerHelper extends Helper
{
    /**
     * @var ClientInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return ClientInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'logger';
    }
}
