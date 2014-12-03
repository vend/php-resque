<?php

namespace Resque\Console\Helper;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\Helper;

/**
 * Logger CLI helper
 */
class LoggerHelper extends Helper
{
    /**
     * @var LoggerInterface
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
     * @return LoggerInterface
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
