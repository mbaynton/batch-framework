<h1>Batch Processing Framework</h1>

[![Build Status](https://travis-ci.org/mbaynton/batch-framework.svg?branch=master)](https://travis-ci.org/mbaynton/batch-framework)
[![Coverage Status](https://coveralls.io/repos/github/mbaynton/batch-framework/badge.svg?branch=master)](https://coveralls.io/github/mbaynton/batch-framework?branch=master)

This library offers foundational algorithms and structures to enable scenarios
where long-running jobs that can be divided into small work units get processed
progressively by successive calls to a PHP script on a webserver. This avoids
exceeding script execution time and network timeout limitations often found in 
web execution environments.

It emphasizes minimal overhead of the framework itself so that jobs
complete as quickly as possible.

Features include:
 * Support for processing the batch of work units across the lifespan of many
   requests when being run in a web environment. This prevents individual
   responses and webserver processes from running longer than is desirable.
 * Efficient determination of when to stop running more work units based on
   past work units' runtimes so that requests complete around a target
   duration.
 * Attention to minimizing the amount of state data and number of trips to a
   backing store that are involved with handing off between reqeusts.
 * Support for parallel execution of embarrasingly parallelizable problems, e.g.
   those where individual work units do not need to communicate or coordinate
   between each other during their execution. See
   [parallelization](#parallelization:-using-multiple-runners) for details.
 * No requirement to use a particular PHP framework, but with an awareness of
   controller and service design patterns.

As this is a library, it offers no functionality "out of the box."

## Dependencies
 * PHP 5.4+
 * `Psr\Http\Message\ResponseInterface` available via Composer, and any 
   implementation of this interface.
   
## Documentation / Examples
The docs here will help start you up writing code that's meant to work with this
framework. If you encounter gaps or questions about the info here, you might want to
refer to the [Curator application on GitHub](http://github.com/curator-wik/curator),
which uses and was written alongside this framework.

Documentation is accurate for `v1.0.0`.

### Terms and their definitions
  * **Runnable**:  
    One of the user-implemented classes that models a long-running job. An instance of a Runnable
    models and provides the implementation for a single unit of work. It is its `run()`
    method whose body does the actual work/computation to further the Task's progress.
  * **Runnable Iterator**:  
    A PHP `\Iterator` (please extend `AbstractRunnableIterator`) that produces `Runnables`
    appropriate to the segment of the overall task that should be performed, given as
    input the `Runner rank` and number of `Runnables` already performed on prior
    incarnations of the `Runner`.    
  * **Runner**:  
    The server-side code that runs the show. The Runner pumps the Runnable iterator for
    new Runnables, launches
    them, monitors the time runnables are taking and the time remaining to decide when
    to stop, dispatches Runnable and Task execution events to Task and Controller
    callbacks, and initiates Runnable and Task intermediate result aggregation.
  * **Runner id**:
    An integer uniquely identifying a given logical `Runner`.
    Clients are expected to create as many corresponding `Runner` requests
    as the framework's current `Task instance state` supports, initially assigning
    a unique integer id that the client has not used before to each of these requests. 
  * **Runner incarnation**:  
    Logically, the framework tries to create the illusion of `n` `Runnable` units of
    work that are executed by`x` `Runners` (concurrently if `x > 1`.) However, in order
    to prevent the HTTP request that started the `Runnable` from remaining incomplete
    for longer than desired, the framework may stop launching new `Runnables`, let
    the `Runner` stop doing work early, and signal the client to make a successive
    request with the same `Runner id`. Each HTTP request that's handled by starting a
    `Runner` bearing the same `Runner id` is called an *incarnation* of the runner with
    that id. All incarnations of a `Runner` also will share the same `Runner rank`.
  * **Runner rank**:  
    A number uniquely identifying a given `Runner` within a Task. If your Task only
    supports one concurrent `Runner`, this will always be `0`. If your `Task` declares
    support for `n` concurrent `Runner`s, this will range from `0` to `n-1`. Differs
    from `Runner id` in that its range is always `0` to `n-1`.
  * **Task**:  
    One of the user-implemented classes that models a long-running job. The `Task`
    serves as a factory for `Runnable Iterator`s, tells the framework what to do
    with results of `Runnable`s, may intervene in the event a `Runnable` experiences
    a throwable error or exception, provides methods to reduce multiple `Runnable` results
    to simpler intermediate results, and provides a method to translate
    the complete `Runnable` results to a `Psr\Http\Message\ResponseInterface`. (Packaging
    the batch run's overall result as a standard HTTP response format enables advanced
    clients such as single-page applications or mobile apps to transparently delegate
    some requests to the batch framework while responding directly to other requests.)
  * **Task instance state**:  
    One of the user-implemented classes that models a long-running job. Task instance
    state captures the variable properties of a given task execution, such as where to
    find inputs to operate on, who (in terms of PHP session id) is currently running
    this `Task`, how large the `Task` is estimated to be (in terms of `Runnable`s), and
    how many concurrent `Runners` the `Task` supports. Typically, one can extend the
    `TaskInstanceState` class, which handles most everything but your task's unique inputs.
    Note that this class is not intended to be used to capture `Runnable` output.
  
This framework primarily provides an implementation of the `Runner` in the class `AbstractRunner`.
A complete system leveraging this library will typically include a concrete extension 
of `AbstractRunner` to interface with your application's persistence layer (e.g.,
database), and a controller or other script making use of the `HttpRunnerControllerTrait`
to  handle incoming requests and interface with your application's session layer.

Each long-running job is coded as the following components:
  - An implementation of `TaskInterface`.
  - An extension of `AbstractRunnableIterator`.
  - An implementation of `RunnableInterface`.

### Parallelization: using multiple runners
Strictly speaking, this framework supports concurrent execution of more than one runnable
from the same Task at a time. But, in order to do concurrent runnables, lots of other
code must support this, too:
  * Your extension of `AbstractRunner` must implement its methods in a concurrency-safe
    manner, especially `AbstractRunner::retrieveRunnerState()` and
    `AbstractRunner::finalizeRunner()` should read and write to their underlying storage
    in a way that does not cause corruption or lost writes should several instances
    for the same Task instance be run simultaneously.
  * Your client must be programmed to send multiple concurrent batch runner requests.
  * The work you want to do must be [embarrasingly parallelizable](https://en.wikipedia.org/wiki/Embarrassingly_parallel).
    Each runnable can produce output, but runnables cannot take other runnables' output
    from the `Task` as input or otherwise interfere with each other if they access
    a shared resource.
  * Your `Task instance state`'s `getNumRunners()` must return more than 1 to declare
    concurrent support for more than 1 `Runner`.
  * The `Runnable iterator` constructed by your `Task` must take the `Runner rank` into
    account and be able to assign a portion of the total `Runnable`s to each `Runner rank`,
    as evenly as possible, with each `Runnable` unit of work being given out to one of the
    `Runner`s exactly once.
  * Your overall application (request controller, etc.) must not be impacted by several
    simultaneous requests from the same user, and must not be holding the [PHP session lock](http://php.net/manual/en/function.session-write-close.php)
    when the runnables are executing.
    

## License
MIT
