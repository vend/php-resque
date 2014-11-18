# Resque for PHP
## Namespaced Fork

[Resque](https://github.com/resque/resque) is a Redis-backed library for creating background jobs, placing
those jobs on one or more queues, and processing them later.

This is a PHP fork of the Resque worker and job classes. This makes it compatible with the 
resque-web interface, and with other Resque libraries. (You could enqueue jobs from Ruby and dequeue
them in PHP, for instance).

This library (`vend/resque`) is a fork of [chrisboulton/php-resque](https://github.com/chrisboulton/php-resque) at around
version 1.3, that has been refactored to remove global state, add namespacing, and improve
decoupling. This makes it easier to use and extend.

## Getting Started ##

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
echo $id; // [0-9a-f]{16}
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

<!--

## Workers ##

A basic "up-and-running" `bin/resque` file is included that sets up a
running worker environment. (`vendor/bin/resque` when installed
via Composer)

The exception to the similarities with the Ruby version of resque is
how a worker is initially setup. To work under all environments,
not having a single environment such as with Ruby, the PHP port makes
*no* assumptions about your setup.

```sh
$ QUEUE=file_serve php bin/resque
```

It's your responsibility to tell the worker which file to include to get
your application underway. You do so by setting the `APP_INCLUDE` environment
variable:

```sh
$ QUEUE=file_serve APP_INCLUDE=../application/init.php php bin/resque
```

*Pro tip: Using Composer? More than likely, you don't need to worry about
`APP_INCLUDE`, because hopefully Composer is responsible for autoloading
your application too!*

Getting your application underway also includes telling the worker your job
classes, by means of either an autoloader or including them.

Alternately, you can always `include('bin/resque')` from your application and
skip setting `APP_INCLUDE` altogether.  Just be sure the various environment
variables are set (`setenv`) before you do.

### Logging ###

The port supports the same environment variables for logging to STDOUT.
Setting `VERBOSE` will print basic debugging information and `VVERBOSE`
will print detailed information.

```sh
$ VERBOSE=1 QUEUE=file_serve bin/resque
$ VVERBOSE=1 QUEUE=file_serve bin/resque
```

### Priorities and Queue Lists ###

Similarly, priority and queue list functionality works exactly
the same as the Ruby workers. Multiple queues should be separated with
a comma, and the order that they're supplied in is the order that they're
checked in.

As per the original example:

```sh
$ QUEUE=file_serve,warm_cache bin/resque
```

The `file_serve` queue will always be checked for new jobs on each
iteration before the `warm_cache` queue is checked.

### Running All Queues ###

All queues are supported in the same manner and processed in alphabetical
order:

```sh
$ QUEUE='*' bin/resque
```

### Running Multiple Workers ###

Multiple workers can be launched simultaneously by supplying the `COUNT`
environment variable:

```sh
$ COUNT=5 bin/resque
```

Be aware, however, that each worker is its own fork, and the original process
will shut down as soon as it has spawned `COUNT` forks.  If you need to keep
track of your workers using an external application such as `monit`, you'll
need to work around this limitation.

### Custom prefix ###

When you have multiple apps using the same Redis database it is better to
use a custom prefix to separate the Resque data:

```sh
$ PREFIX=my-app-name bin/resque
```

### Forking ###

Similarly to the Ruby versions, supported platforms will immediately
fork after picking up a job. The forked child will exit as soon as
the job finishes.

The difference with php-resque is that if a forked child does not
exit nicely (PHP error or such), php-resque will automatically fail
the job.

### Signals ###

Signals also work on supported platforms exactly as in the Ruby
version of Resque:

* `QUIT` - Wait for job to finish processing then exit
* `TERM` / `INT` - Immediately kill job then exit
* `USR1` - Immediately kill job but don't exit
* `USR2` - Pause worker, no new jobs will be processed
* `CONT` - Resume worker.

### Process Titles/Statuses ###

The Ruby version of Resque has a nifty feature whereby the process
title of the worker is updated to indicate what the worker is doing,
and any forked children also set their process title with the job
being run. This helps identify running processes on the server and
their resque status.

**PHP does not have this functionality by default until 5.5.**

A PECL module (<http://pecl.php.net/package/proctitle>) exists that
adds this functionality to PHP before 5.5, so if you'd like process
titles updated, install the PECL module as well. php-resque will
automatically detect and use it.

<<<<<<< HEAD
-->

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
