<?php


namespace mbaynton\BatchFramework\Tests\Mocks;


use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use mbaynton\BatchFramework\RunnableInterface;

class RunnerControllerMock implements RunnerControllerInterface {
  protected $num_runnables_left;
  protected $should_continue_running = TRUE;

  /**
   * RunnerControllerMock constructor.
   * @param int $num_runnables_per_incarnation
   *   Number of Runnables to process before shouldContinueRunning() is FALSE.
   *   Values less than 0 allow infinite Runnables.
   */
  public function __construct($num_runnables_per_incarnation) {
    $this->num_runnables_left = $num_runnables_per_incarnation;
  }

  public function onRunnableComplete(RunnableInterface $runnable, $result) {
    $this->num_runnables_left--;
  }

  public function onRunnableError(RunnableInterface $runnable, $exception) {
    $this->num_runnables_left--;
  }

  public function onBeforeRunnableStarted(RunnableInterface $runnable) {
    // TODO: Implement onBeforeRunnableStarted() method.
  }

  public function shouldContinueRunning() {
    if ($this->num_runnables_left !== 0 && $this->should_continue_running) {
      return TRUE;
    } else {
      $this->should_continue_running = FALSE;
      return FALSE;
    }
  }
}
