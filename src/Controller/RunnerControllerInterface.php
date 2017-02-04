<?php


namespace mbaynton\BatchFramework\Controller;

use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;

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

  function onBeforeRunnableStarted(RunnableInterface $runnable);

  function onRunnableComplete(RunnableInterface $runnable, $result, ProgressInfo $progress);

  function onRunnableError(RunnableInterface $runnable, $exception, ProgressInfo $progress);

}
