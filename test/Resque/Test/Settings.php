<?php

namespace Resque\Test;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Resque\ClientInterface;
use RuntimeException;

class Settings implements LoggerAwareInterface
{
    protected $clientType;
    protected $host;
    protected $bind;
    protected $port;
    protected $db;
    protected $prefix;
    protected $buildDir;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        $this->testPid = getmypid();
        $this->fromDefaults();
    }

    protected function fromDefaults()
    {
        $this->buildDir   = __DIR__ . '/../../build';
        $this->port       = '6379';
        $this->host       = 'localhost';
        $this->bind       = '127.0.0.1';
        $this->db         = 0;
        $this->prefix     = '';
        $this->clientType = 'predis';
    }

    public function fromEnvironment()
    {
        $env = array(
            'client_type' => 'clientType',
            'host'        => 'host',
            'port'        => 'port',
            'bind'        => 'bind',
            'build_dir'   => 'buildDir',
            'run'         => 'run',
            'db'          => 'db',
            'prefix'      => 'prefix'
        );

        foreach ($env as $var => $setting) {
            $name = 'RESQUE_' . strtoupper($var);

            if (isset($_SERVER[$name])) {
                $this->$setting = $_SERVER[$name];
            }
        }
    }

    public function setBuildDir($dir)
    {
        $this->buildDir = $dir;
    }

    public function startRedis()
    {
        $this->checkBuildDir();
        $this->dumpRedisConfig();
        $this->registerShutdown();

        $this->logger->notice('Starting redis server in {buildDir}', array('buildDir' => $this->buildDir));
        exec('cd ' . $this->buildDir . '; redis-server ' . $this->buildDir . '/redis.conf', $output, $return);
        usleep(500000);

        if ($return != 0) {
            throw new \RuntimeException('Cannot start redis-server');
        }
    }

    protected function getRedisConfig()
    {
        return array(
            'daemonize'  => 'yes',
            'pidfile'    => './redis.pid',
            'port'       => $this->port,
            'bind'       => $this->bind,
            'timeout'    => 0,
            'dbfilename' => 'dump.rdb',
            'dir'        => $this->buildDir,
            'loglevel'   => 'debug',
            'logfile'    => './redis.log'
        );
    }

    /**
     * @return ClientInterface
     * @throws \InvalidArgumentException
     */
    public function getClient()
    {
        switch ($this->clientType) {
            case 'predis':
                return new \Predis\Client(array(
                    'host'   => $this->host,
                    'port'   => $this->port,
                    'db'     => $this->db,
                    'prefix' => $this->prefix
                ));
            case 'phpiredis':
                return new \Predis\Client(array(
                    'host'   => $this->host,
                    'port'   => $this->port,
                    'db'     => $this->db,
                    'prefix' => $this->prefix
                ), array(
                    'tcp'  => 'Predis\Connection\PhpiredisStreamConnection',
                    'unix' => 'Predis\Connection\PhpiredisSocketConnection'
                ));
            case 'credis':
            case 'phpredis':
                $client = new \Resque\Client\CredisClient($this->host, $this->port);
                $client->setCloseOnDestruct(false);
                return $client;
            default:
                throw new \InvalidArgumentException('Invalid or unknown client type: ' . $this->clientType);
        }
    }

    protected function checkBuildDir()
    {
        if (!is_dir($this->buildDir)) {
            mkdir($this->buildDir);
        }

        if (!is_dir($this->buildDir)) {
            throw new RuntimeException('Could not create build dir: ' . $this->buildDir);
        }
    }

    protected function dumpRedisConfig()
    {
        $file = $this->buildDir . '/redis.conf';
        $conf = '';

        foreach ($this->getRedisConfig() as $name => $value) {
            $conf .= "$name $value\n";
        }

        $this->logger->info('Dumping redis config to {file}', array('file' => $file));
        $this->logger->debug($conf);

        file_put_contents($file, $conf);
    }

    // Override INT and TERM signals, so they do a clean shutdown and also
    // clean up redis-server as well.
    public function catchSignals()
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->logger->debug('SIGINT received');
                exit;
            });

            pcntl_signal(SIGTERM, function () {
                $this->logger->debug('SIGTERM received');
                exit;
            });
        }
    }

    public function killRedis()
    {
        $pid = getmypid();

        $this->logger->notice('Attempting to kill redis from {pid}', array('pid' => $pid));

        if ($pid === null || $this->testPid !== $pid) {
            $this->logger->warning('Refusing to kill redis from forked worker');
            return; // don't kill from a forked worker
        }

        $pidFile = $this->buildDir . '/redis.pid';

        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            posix_kill((int) $pid, 9);

            if(is_file($pidFile)) {
                unlink($pidFile);
            }
        }

        $filename = $this->buildDir . '/dump.rdb';

        if (is_file($filename)) {
            unlink($filename);
        }
    }

    protected function registerShutdown()
    {
        $this->logger->info('Registered shutdown function');
        register_shutdown_function(array($this, 'killRedis'));
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
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
}
