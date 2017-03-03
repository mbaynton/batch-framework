<?php


namespace mbaynton\BatchFramework;


abstract class AbstractRunnable implements RunnableInterface {
  /**
   * @var TaskInterface $parent_task
   */
  protected $parent_task;

  /**
   * @var int $runnable_id
   */
  protected $runnable_id;

  public function __construct($runnable_id) {
    $this->runnable_id = $runnable_id;
  }

  public function getId() {
    return $this->runnable_id;
  }

  abstract function run(TaskInterface $task, TaskInstanceStateInterface $instance_state);
}
