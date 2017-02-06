<?php

namespace mbaynton\BatchFramework\Tests\Unit\Controller;

use mbaynton\BatchFramework\Controller\HttpRunnerControllerTrait;
use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\Internal\FunctionWrappers;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\ScheduledTaskInterface;

/**
 * Class HttpRunnerController
 *   A minimal class to test the functonality of HttpRunnerControllerTrait.
 */
class HttpRunnerController implements RunnerControllerInterface  {
  use HttpRunnerControllerTrait;

  public function __construct($abort_behavior, FunctionWrappers $wrappers, RunnerInterface $runner) {
    $this->_fnwrap = $wrappers;
    $this->onCreate($abort_behavior, $runner);
  }

  // AbstractRunner's proper use of RunnerControllerInterface tested elsewhere.
  public function onRunnableComplete(RunnableInterface $runnable, $result, ProgressInfo $progress) {}

  public function onBeforeRunnableStarted(RunnableInterface $runnable) {}

  public function onRunnableError(RunnableInterface $runnable, $exception, ProgressInfo $progress) {}

  public function onTaskComplete(ScheduledTaskInterface $task) {}

}
