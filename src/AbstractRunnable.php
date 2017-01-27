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

  public function __construct(TaskInterface $parent_task, $runnable_id) {
    $this->parent_task = $parent_task;
    $this->runnable_id = $runnable_id;
  }

  public function getId() {
    return $this->runnable_id;
  }

  public function getTask() {
    return $this->parent_task;
  }

  abstract function run();
}
