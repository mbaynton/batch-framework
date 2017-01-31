<?php


namespace mbaynton\BatchFramework\Tests\Mocks;

use mbaynton\BatchFramework\AbstractRunnable;

class RunnableMock extends AbstractRunnable {

  public function __construct(TaskMock $parent_task, $runnable_id) {
    parent::__construct($parent_task, $runnable_id);
  }

  public function run() {
    /**
     * @var TaskMock $task
     */
    $task = $this->getTask();
    $operation = $task->static_task_callable;
    $operation($this->runnable_id);
  }
}
