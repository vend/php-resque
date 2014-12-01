# Resque for PHP
## Namespaced Fork

[![Build Status](https://travis-ci.org/vend/php-resque.svg?branch=master)](https://travis-ci.org/vend/php-resque)
[![Latest Stable Version](https://poser.pugx.org/vend/resque/v/stable.svg)](https://packagist.org/packages/vend/resque)
[![Latest Unstable Version](https://poser.pugx.org/vend/resque/v/unstable.svg)](https://packagist.org/packages/vend/resque)
[![License](https://poser.pugx.org/vend/resque/license.svg)](https://packagist.org/packages/vend/resque)

[Resque](https://github.com/resque/resque) is a Redis-backed library for creating background jobs, placing
those jobs on one or more queues, and processing them later.

This is a PHP fork of the Resque worker and job classes. This makes it compatible with the
resque-web interface, and with other Resque libraries. (You could enqueue jobs from Ruby and dequeue
them in PHP, for instance).

This library (`vend/resque`) is a fork of [chrisboulton/php-resque](https://github.com/chrisboulton/php-resque) at around
version 1.3, that has been refactored to remove global state, add namespacing, and improve
decoupling. This makes it easier to use and extend.

## Getting Started

Add `vend/resque` to your application's composer.json.

```json
{
    "require": {
        "vend/resque": "~2.1.0"
    }
}
```

## Requirements

* PHP 5.3+
* A Redis client library (for instance, [Predis](https://github.com/nrk/predis) or [Credis](https://github.com/colinmollenhour/credis))

## Jobs

### Queueing Jobs

Jobs are queued as follows:

```php
use Resque\Resque;
use Predis\Client;

$resque = new Resque(new Client());
$resque->enqueue('default_queue', 'App\Job', array('foo' => 'bar'), true);
```

In order the arguments are: queue, job class, job payload, whether to enable tracking.

### Defining Jobs

Each job should be in its own class, and implement the `Resque\JobInterface`. (This is pretty easy,
and only really requires a single custom method: `perform()`.) Most of the time, you'll want to
use the default implementation of a Job, and extend the `Resque\AbstractJob` instead of implementing
the interface yourself:

```php
namespace App;

use Resque\AbstractJob;

class Job extends AbstractJob
{
    public function perform()
    {
        // work work work
        $this->doSomething($this->payload['foo']);
    }
}
```

Any exception thrown by a job will result in the job failing - be
careful here and make sure you handle the exceptions that shouldn't
result in a job failing.

### Job Status Tracking

vend/resque has the ability to perform basic status tracking of a queued
job. The status information will allow you to check if a job is in the
queue, is currently being run, has finished, or has failed.

To track the status of a job, pass `true` as the fourth argument to
`Resque\Resque::enqueue`. An ID used for tracking the job status will be
returned:

```php
$id = $resque->enqueue('default_queue', 'App\Job', $payload, true);
echo $id; // [0-9a-f]{32}
```

To fetch the status of a job:

```php
$factory = new Resque\Job\StatusFactory($resque);

// Pass the ID returned from enqueue
$status = $factory->getForId($id);

// Alternatively, to get the status for a Job instance:
$status = $factory->getForJob($job);

// Outputs the status as a string: 'waiting', 'running', 'complete', etc.
echo $status->getStatus();
```

The Status object contains methods for adding other attributes to the
tracked status. (For instance, you might use the status object to track
errors, completion information, iterations, etc.)

#### Statuses

Job statuses are defined as constants in the `Resque\Job\Status` class.
Valid statuses include:

* `Resque\Job\Status::STATUS_WAITING` - Job is still queued
* `Resque\Job\Status::STATUS_RUNNING` - Job is currently running
* `Resque\Job\Status::STATUS_FAILED` - Job has failed
* `Resque\Job\Status::STATUS_COMPLETE` - Job is complete

Statuses are available for up to 24 hours after a job has completed
or failed, and are then automatically expired. A status can also
forcefully be expired by calling the `stop()` method on a status
class.

## Console

You are free you implement your own daemonization/worker pool strategy by
subclassing the `Worker` class. For playing around, and low throughput
applications, you might like to use the built-in console commands.

This version of the library uses the Symfony2 Console component. But it
must be configured with the details of your Redis connection. We do this
in much the same way that the Doctrine2 Console component gets details
of your database connection: via a `cli-config.php` file.

There is an example `cli-config.php` in this repository.

If you're running a full-stack web application, you'd generally use your
locator/service container to fill in the Redis client connection in this
file. (The default is to use Predis, and to connect to 127.0.0.1:6379).

### Basic Usage

```
resque <subcommand>

Available commands:
  enqueue       Enqueues a job into a queue
  help          Displays help for a command
  list          Lists commands
  worker        Runs a Resque worker
queue
  queue:clear   Clears a specified queue
  queue:list    Outputs information about queues
```

### Enqueueing

This command will enqueue a `Some\Test\Job` job onto the `default` queue.
Watch out for the single quotes around the class name: when specifying backslashes
on the command line, you'll probably have to avoid your shell escaping them.

```
resque enqueue default 'Some\Test\Job' -t
```

### Worker

This command will run a simple pre-forking worker on two queues:

```
resque worker -Q default -Q some_other_queue
```

(`-q` means quiet, `-Q` specifies queues). You can also specify no queues, or
the special queue `'*'` (watch for shell expansion).


### Queue Information

There are a couple of useful commands for getting information about the queues. This
will show a list of queues and how many jobs are waiting on each:

```
resque queue:list
```

This command will clear a specified queue:

```
resque queue:clear default
```

### Logging

The library now uses PSR3. When running as a console component, you can customise
the logger to use in `cli-config.php`. (For instance, you might like to send your
worker logs to Monolog.)

## Why Fork?

Unfortunately, several things about the existing versions of php-resque made it
a candidate for refactoring:

* php-resque supported the Credis connection library and had hard-coded this
  support by using static accessors. This meant more advanced features such as
  replication and pipelining were unavailable.
  * Now, Resque for PHP supports any client object that implements a suitable
    subset of Redis commands.  No type-checking is done on the passed in connection,
    meaning you're free to use Predis, or whatever you like.
* While the public API of `php-resque` was alright, the protected API was pretty
  much useless. This made it hard to extend worker classes to dispatch jobs differently.
* Important state (the underlying connection) was thrown into the global scope
  by hiding it behind static accessors (`Resque::redis()`). This makes things
  easier for the library author (because he/she need not think about dependencies)
  but also statically ties together the classes in the library: it makes
  testing and extending the library hard.
  * There's no reason to do this: `Resque` instances should simply use DI and
    take a client connection as a required constructor argument.
  * This improvement also allows the connection to be mocked without extending
    the `Resque` class.
  * And it lets you reuse your existing connection to Redis, if you have one.
    No need to open a new connection just to enqueue a job.
* Statistic classes were static for no good reason.
  * To work, statistics need a connection to Redis, a name, and several methods
    (get, set). State and methods to manipulate it? Sounds like a task for
    objects! *Not* just static methods, that don't encapsulate any of the state.
  * Because these were static calls, the `Resque_Stat` class was hard-coded into
    several other classes, and could not be easily extended.
* The library is now fully namespaced and compatible with PSR-0. The top level
    namespace is `Resque`.
* The events system has been removed. There is now little need for it.
  It seems like the events system was just a workaround due to the poor
  extensibility of the Worker class. The library should allow you to extend any
  class you like, and no method should be too long or arduous to move into a
  subclass.

## Contributors ##

Here's the contributor list from earlier versions, at [chrisboulton/php-resque](https://github.com/chrisboulton/php-resque):

* @chrisboulton
* @acinader
* @ajbonner
* @andrewjshults
* @atorres757
* @benjisg
* @cballou
* @chaitanyakuber
* @charly22
* @CyrilMazur
* @d11wtq
* @danhunsaker
* @dceballos
* @ebernhardson
* @hlegius
* @hobodave
* @humancopy
* @JesseObrien
* @jjfrey
* @jmathai
* @joshhawthorne
* @KevBurnsJr
* @lboynton
* @maetl
* @matteosister
* @MattHeath
* @mickhrmweb
* @Olden
* @patrickbajao
* @pedroarnal
* @ptrofimov
* @rajibahmed
* @richardkmiller
* @Rockstar04
* @ruudk
* @salimane
* @scragg0x
* @scraton
* @thedotedge
* @tonypiper
* @trimbletodd
* @warezthebeef
