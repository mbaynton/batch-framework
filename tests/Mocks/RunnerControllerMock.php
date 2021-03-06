<?php


namespace mbaynton\BatchFramework\Tests\Mocks;


use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;

class RunnerControllerMock implements RunnerControllerInterface {
  protected $num_runnables_left;
  protected $should_continue_running = TRUE;

  /**
   * @var int $num_on_before_started
   */
  protected $num_on_before_started = 0;

  /**
   * @var int $num_on_complete
   */
  protected $num_on_complete;

  /**
   * @var int $num_on_error
   */
  protected $num_on_error;

  protected $num_task_complete = 0;

  public function onRunnableComplete(RunnableInterface $runnable, $result, ProgressInfo $progress) {
    $this->num_runnables_left--;
    $this->num_on_complete++;
  }

  public function onRunnableError(RunnableInterface $runnable, \Exception $exception, ProgressInfo $progress) {
    $this->num_runnables_left--;
    $this->num_on_error++;
  }

  public function onBeforeRunnableStarted(RunnableInterface $runnable) {
    $this->num_on_before_started++;
  }

  public function onTaskComplete(TaskInstanceStateInterface $task) {
    $this->num_task_complete++;
  }

  /*
   * Controller no longer holds primary responsibility for deciding how many
   * Runnables to execute -- in order to test AbstractRunner's internal logic,
   * we do not limit Runnables in this mock anymore.
   */
  public function shouldContinueRunning() {
    return TRUE;
  }

  public function getNumCalls_onRunnableComplete() {
    return $this->num_on_complete;
  }

  public function getNumCalls_onBeforeRunnableStarted() {
    return $this->num_on_before_started;
  }

  public function getNumCalls_onRunnableError() {
    return $this->num_on_error;
  }

  public function getNumCalls_onTaskComplete() {
    return $this->num_task_complete;
  }
}
