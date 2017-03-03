<?php


namespace mbaynton\BatchFramework\Tests\Mocks;

use mbaynton\BatchFramework\AbstractRunnable;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;

class RunnableMock extends AbstractRunnable {

  public function __construct(TaskMock $parent_task, $runnable_id) {
    parent::__construct($runnable_id);
  }

  public function run(TaskInterface $task, TaskInstanceStateInterface $instance_state) {
    /**
     * @var TaskMock $task
     */
    $operation = $task->static_task_callable;
    $operation($this->runnable_id);
  }
}
