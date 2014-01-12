<?php

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->add('Resque', __DIR__);

$build = __DIR__ . '/../build';

$logger = new Monolog\Logger('test');
$logger->pushHandler(new Monolog\Handler\StreamHandler($build . '/test.log'));

$settings = new Resque\Test\Settings();
$settings->setLogger($logger);
$settings->fromEnvironment();
$settings->setBuildDir($build);
$settings->catchSignals();
$settings->startRedis(); // registers shutdown function

Resque\Test::setSettings($settings);
