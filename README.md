<div style="float: right">
<img src="https://travis-ci.org/mbaynton/batch-framework.svg?branch=master" alt="Build Status">
</div>
<h1>Batch Processing Framework</h1>
This library offers foundational algorithms and structures to handle scenarios
where long-running jobs can be divided into small work units. It emphasizes
minimal overhead of the framework itself so that jobs complete as quickly as
possible.

Features include:
 * Support for processing the batch of work units across the lifespan of many
   requests when being run in a web environment. This prevents individual
   responses and webserver processes from running longer than is desirable.
 * Efficient determination of when to stop running more work units based on
   past work units' runtimes so that requests complete around a target
   duration.
 * Attention to minimizing the amount of state data and number of trips to a
   backing store that are involved with handing off between reqeusts.
 * Support for parallel execution of trivially parallelizable problems, e.g.
   those where individual work units do not need to communicate or coordinate
   between each other during their execution. This requires clients that send
   simultaneous requests.
 * No requirement to use a particular PHP framework, but with an awareness of
   controller and service design patterns.

As this is a library, it offers no functionality "out of the box." A complete
system will typically include a concrete extension of `AbstractRunner` to
interface with your application's persistence layer (e.g., database), a
controller or other script making use of the `HttpRunnerControllerTrait` to 
handle incoming requests and interface with your application's session layer,
and one or more `TaskInterface`/`Iterator` of Runnables/`RunnableInterface`
implementations that contain the code specific to particular long-running jobs.

APIs are subject to change until version 1.0.0.

## Requirements
 * PHP 5.4+
 * `Psr\Http\Message\ResponseInterface`, available via Composer.

## License
MIT
