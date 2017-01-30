<?php

namespace mbaynton\BatchFramework;


class ScheduledTask implements ScheduledTaskInterface {
  /**
   * @var TaskInterface $task
   */
  protected $task;

  /**
   * @var int $task_id
   */
  protected $task_id;

  protected $runner_ids = NULL;

  /**
   * @var string $session_id
   */
  protected $session_id;

  /**
   * ScheduledTask constructor.
   *
   * @param TaskInterface $task
   * @param int $task_id
   * @param int[] $runner_ids
   * @param string $session_id
   */
  public function __construct(TaskInterface $task, $task_id, $runner_ids, $session_id) {
    $this->task = $task;
    $this->task_id = $task_id;
    if ($this->task->getMaxRunners() > 0) {
      $this->runner_ids = array_splice($runner_ids, 0, min($this->getNumRunners(), $this->task->getMaxRunners()));
    } else {
      $this->runner_ids = $runner_ids;
    }
    $this->session_id = $session_id;
  }

  public function getNumRunners() {
    // Override this method if you need to adjust.
    if ($this->runner_ids === NULL) {
      return 4;
    } else {
      return count($this->runner_ids);
    }
  }

  public function getRunnerIds() {
    return $this->runner_ids;
  }

  public function getTask() {
    return $this->task;
  }

  public function getOwnerSession() {
    return $this->session_id;
  }

  public function getTaskId() {
    return $this->task_id;
  }
}
