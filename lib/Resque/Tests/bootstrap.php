<?php

require_once __DIR__ . '/../../SplClassLoader.php';

$classLoader = new SplClassLoader('Resque', __DIR__ . '/../../');
$classLoader->register();
