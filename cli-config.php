<?php

// Configure your autoloading here: if you're just using Composer, this should be fine
(@include_once __DIR__ . '/vendor/autoload.php') || @include_once __DIR__ . '/../../autoload.php';

// Configure the client however you'd like, here
// Or, grab it from your application's container
use Predis\Client;

/* @var \Resque\Client\ClientInterface $predis */
$predis = new Client(array(
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379
));

return \Resque\Console\ConsoleRunner::createHelperSet($predis);
