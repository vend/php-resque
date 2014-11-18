<?php

use Predis\Client;

// Configure the client however you'd like, here...
$predis = new Client();

return \Resque\Console\ConsoleRunner::createHelperSet($predis);
