<?php


namespace mbaynton\BatchFramework\Controller;

use mbaynton\BatchFramework\RunnableInterface;

/**
 * Interface RunnerControllerInterface
 *   Provided to RunnerInterface implementations to influence their operation.
 */
interface RunnerControllerInterface {
  /**
   * Tests whether the Runner should process another Runnable.
   *
   * RunnerInterface implementations should test this before each Runnable.
   *
   * @return bool
   */
  function shouldContinueRunning();

  function onBeforeRunnableStarted(RunnableInterface $runnable);

  function onRunnableComplete(RunnableInterface $runnable, $result);

  function onRunnableError(RunnableInterface $runnable, $exception);

}
