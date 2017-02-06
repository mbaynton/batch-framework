<?php


namespace mbaynton\BatchFramework\Controller;

use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\ScheduledTaskInterface;

/**
 * Interface RunnerControllerInterface
 *   Provided to RunnerInterface implementations to influence their operation.
 */
interface RunnerControllerInterface {
  /**
   * Tests whether the Runner should continue processing Runnables.
   *
   * RunnerInterface implementations should test this at reasonable intervals.
   *
   * @return bool
   */
  function shouldContinueRunning();

  /**
   * Called immediately before each Runnable begins executing.
   *
   * @param \mbaynton\BatchFramework\RunnableInterface $runnable
   *   The Runnable about to be executed.
   *
   * @return void
   */
  function onBeforeRunnableStarted(RunnableInterface $runnable);

  /**
   * Called immediately after a Runnable finishes executing without exceptions.
   *
   * @param RunnableInterface $runnable
   *   The Runnable that finished executing.
   * @param $result
   *   The value returned from the Runnable's run() method.
   * @param ProgressInfo $progress
   *   Data describing the progress of this incarnation of the Runner.
   *
   * @return void
   */
  function onRunnableComplete(RunnableInterface $runnable, $result, ProgressInfo $progress);

  /**
   * Called immediately after a Runnable throws an exception.
   *
   * @param RunnableInterface $runnable
   *   The Runnable whos run() method threw an exception.
   * @param $exception
   *   The exception the Runnable threw.
   * @param ProgressInfo $progress
   *   Data describing the progress of this incarnation of the Runner.
   *
   * @return void
   */
  function onRunnableError(RunnableInterface $runnable, $exception, ProgressInfo $progress);

  /**
   * Called once per Task, after all Runnables have been processed.
   *
   * Provides an opportunity to do session state cleanup, etc.
   *
   * @param ScheduledTaskInterface $task
   *   The scheduled task that has completed.
   *
   * @return void
   */
  function onTaskComplete(ScheduledTaskInterface $task);

}
