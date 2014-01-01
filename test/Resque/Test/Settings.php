<?php

namespace Resque\Test;

use Resque\Client;
use RuntimeException;

class Settings
{
    protected $clientType;
    protected $host;
    protected $bind;
    protected $port;
    protected $db;
    protected $prefix;
    protected $buildDir;

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

        foreach ($env as $var => $setting)
        {
            if (isset($_ENV[$var])) {
                $this->$setting = $_ENV[$var];
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

        exec('cd ' . $this->buildDir . '; redis-server ' . $this->buildDir . '/redis.conf', $output, $return);
        usleep(500000);

        if ($return != 0) {
            throw new \RuntimeException('Cannot start redis-server');
        }
    }

    protected function getRedisConfig()
    {
        return array(
            "daemonize"  => "yes",
            "pidfile"    => "./redis.pid",
            "port"       => $this->port,
            "bind"       => $this->bind,
            "timeout"    => 300,
            "dbfilename" => "dump.rdb",
            "dir"        => $this->buildDir,
            "loglevel"   => "debug",
        );
    }

    /**
     * @return Client
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
            case 'credis':
                return new \Credis_Client($this->host, $this->port);
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
        $conf = '';

        foreach ($this->getRedisConfig() as $name => $value)
        {
            $conf .= "$name $value\n";
        }

        file_put_contents($this->buildDir . '/redis.conf', $conf);
    }

    // Override INT and TERM signals, so they do a clean shutdown and also
    // clean up redis-server as well.
    public function catchSignals()
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                exit;
            });

            pcntl_signal(SIGTERM, function () {
                exit;
            });
        }
    }

    public function killRedis($pid = null)
    {
        if ($pid === null || $this->testPid !== $pid) {
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
        register_shutdown_function(array($this, 'killRedis'), $this->testPid);
    }
}
