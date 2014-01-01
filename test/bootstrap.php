<?php

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->add('Resque', __DIR__);

$settings = new Resque\Test\Settings();
$settings->fromEnvironment();
$settings->setBuildDir(__DIR__ . '/../build');
$settings->catchSignals();
$settings->startRedis();

Resque\Test::setSettings($settings);