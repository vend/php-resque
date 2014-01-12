## Forked version to add namespacing

### 2.0.0 ##

**Note:**: vend/php-resque introduces namespacing and other backward incompatible changes to the API.

The code is still released under the same license. With vend/php-resque, we've
chosen to increment the major version number to reflect the changes, but this
is a fork that we use at Vend, where we use Predis, and using the original
chrisboulton/php-resque version wasn't an option.

#### Namespacing

All resque code is loaded from the `Resque` namespace. This means a slight
change in layout to support PSR-0 (given, for instance, `Resque` is now
`Resque\Resque`)

#### Backend coupling removed

Direct Credis coupling has been removed: Resque is now free of coupling to any Redis
client library, meaning you can use it with Predis, Credis, redisent, etc.

Instead of this library creating a Redis client instance for you, you'll be
expected to provide a Redis client object to the Resque instance
as a dependency. This helps eliminate global state, and means the library can
be readily integrated into, for example, your existing Depedency Injection
container or factory methods.

For Credis clients, a lightweight wrapper is provided (this is chiefly used to
implement a reconnect method). Predis clients can be
used directly with no wrapping class. With these two main client types,
support for phpiredis, phpredis
and webdis is provided indirectly.

You can use any client that implements the *methods* in Resque\Client\ClientInterface
 - your client need not *actually* implement the interface (many Redis clients
 use `__call()`).

#### Protected API improvements and general SOLIDness

Hidden global state removed: connection state is no longer hidden behind
Resque::redis() static call. The Resque object is now instantiated like any
normal object, and uses dependency injection in its constructor.

The mostly private API of the previous versions has been opened up
considerably, meaning more opportunity for extending classes. At Vend, we use
this to send jobs to an FPM pool of workers, rather than forking for every job.

#### Worker shuffles queues

The worker shuffles the queues it will dequeue from on every pass of the main loop.
This means each queue a worker runs has an equal chance to be dequeued from. (The
previous behaviour was to work the queues in the order specified).

#### Statistic object wraps main value

Gone is the static `Statistic::get/set` API. A Statistic object now wraps the counter
value, which can be retrieved via `$statistic->get()`.

#### Job implementation made more flexible

Jobs now only need implement JobInterface. They can optionally do this by extending
AbstractJob. Job instances are directly created by the Worker, rather than being attached
to a tightly-coupled concrete Job implementation.

## chrisboulton/php-resque

### 1.3 (2013-??-??) ##

#### Redisent (Redis Library) Replaced with Credis

Redisent has always been the Redis backend for php-resque because of its lightweight nature. Unfortunately, Redisent is largely unmaintained.

[Credis](https://github.com/colinmollenhour/credis) is a fork of Redisent, which among other improvements will automatically use the [phpredis](https://github.com/nicolasff/phpredis) native PHP extension if it is available. (you want this for speed, trust me)

php-resque now utilizes Credis for all Redis based operations. Credis automatically required and installed as a Composer dependency.

#### Composer Support

Composer support has been improved and is now the recommended method for including php-resque in your project. Details on Composer support can be found in the Getting Started section of the readme.

#### Other Improvements/Changes

* **COMPATIBILITY BREAKING**: The bundled worker manager `resque.php` has been moved to `bin/resque`, and is available as `vendor/bin/resque` when php-resque is installed as a Composer package.
* Restructure tests and test bootstrapping. Autoload tests via Composer (install test dependencies with `composer install --dev`)
* Add `SETEX` to list of commands which supply a key as the first argument in Redisent (danhunsaker)
* Fix an issue where a lost connection to Redis could cause an infinite loop (atorres757)
* Add a helper method to `Resque_Redis` to remove the namespace applied to Redis keys (tonypiper)

### 1.2 (2012-10-13)

**Note:** This release is largely backwards compatible with php-resque 1.1. The next release will introduce backwards incompatible changes (moving from Redisent to Credis), and will drop compatibility with PHP 5.2.

* Allow alternate redis database to be selected when calling setBackend by supplying a second argument (patrickbajao)
* Use `require_once` when including php-resque after the app has been included in the sample resque.php to prevent include conflicts (andrewjshults)
* Wrap job arguments in an array to improve compatibility with ruby resque (warezthebeef)
* Fix a bug where the worker would spin out of control taking the server with it, if the redis connection was interrupted even briefly. Use SIGPIPE to trap this scenario cleanly. (d11wtq)
* Added support of Redis prefix (namespaces) (hlegius)
* When reserving jobs, check if the payload received from popping a queue is a valid object (fix bug whereby jobs are reserved based on an erroneous payload) (salimane)
* Re-enable autoload for class_exists in Job.php (humancopy)
* Fix lost jobs when there is more than one worker process started by the same parent process (salimane)
* Move include for resque before APP_INCLUDE is loaded in, so that way resque is available for the app
* Avoid working with dirty worker IDs (salimane)
* Allow UNIX socket to be passed to Resque when connecting to Redis (pedroarnal)
* Fix typographical errors in PHP docblocks (chaitanyakuber)
* Set the queue name on job instances when jobs are executed (chaitanyakuber)
* Fix and add tests for Resque_Event::stopListening (ebernhardson)
* Documentation cleanup (maetl)
* Pass queue name to afterEvent callback
* Only declare RedisException if it doesn't already exist (Matt Heath)
* Add support for Composer
* Fix missing and incorrect paths for Resque and Resque_Job_Status classes in demo (jjfrey)
* Disable autoload for the RedisException class_exists call (scragg0x)
* General tidy up of comments and files/folders

### 1.1 (2011-03-27)

* Update Redisent library for Redis 2.2 compatibility. Redis 2.2 is now required. (thedotedge)
* Trim output of `ps` to remove any prepended whitespace (KevBurnsJr)
* Use `getenv` instead of `$_ENV` for better portability across PHP configurations (hobodave)
* Add support for sub-second queue check intervals (KevBurnsJr)
* Ability to specify a cluster/multiple redis servers and consistent hash between them (dceballos)
* Change arguments for jobs to be an array as they're easier to work with in PHP.
* Implement ability to have setUp and tearDown methods for jobs, called before and after every single run.
* Fix `APP_INCLUDE` environment variable not loading correctly.
* Jobs are no longer defined as static methods, and classes are instantiated first. This change is NOT backwards compatible and requires job classes are updated.
* Job arguments are passed to the job class when it is instantiated, and are accessible by $this->args. This change will break existing job classes that rely on arguments that have not been updated.
* Bundle sample script for managing php-resque instances using monit
* Fix undefined variable `$child` when exiting on non-forking operating systems
* Add `PIDFILE` environment variable to write out a PID for single running workers

### 1.0 (2010-04-18) ##

* Initial release
