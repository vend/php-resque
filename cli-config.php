<?php

/**
 * CLI Config file
 *
 * This file is sourced by the Resque command line tools. It should be copied out to the project that wants to use
 * Resque, and modified to suit. The only important thing is that this file returns a HelperSet, with a 'redis' helper,
 * so that Resque can talk to your Redis instance.
 */

/*
 * Configure your application's bootstrap/autoloading here. If
 * you're just using Composer, this should be enough to give
 * you access to your application's classes.
 */
(@include_once __DIR__ . '/vendor/autoload.php') || @include_once __DIR__ . '/../../autoload.php';

/*
 * Configure the client however you'd like, here. Or, you could
 * grab it from your application's service/injection container.
 */
use Predis\Client;

/* @var \Resque\Client\ClientInterface $predis */
$predis = new Client(array(
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379
));

/*
 * You can optionally customize the PSR3 logger used on the CLI. The
 * default is the Console component's ConsoleLogger
 *
 * $logger = new Monolog\Logger('resque');
 */

return \Resque\Console\ConsoleRunner::createHelperSet($predis/*, $logger*/);
