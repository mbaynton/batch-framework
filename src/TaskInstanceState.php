<?php

namespace mbaynton\BatchFramework;


class TaskInstanceState implements TaskInstanceStateInterface {
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
   * @var int $num_runnables
   */
  protected $num_runnables;

  /**
   * @var int $num_runnables_delta;
   */
  protected $num_runnables_delta;

  /**
   * @var bool $has_updates
   */
  protected $has_updates;

  /**
   * TaskState constructor.
   *
   * @param int $task_id
   * @param string $session_id
   * @param int $num_runnables_estimate
   * @param int $max_runners
   */
  public function __construct($task_id, $session_id, $num_runnables_estimate) {
    $this->task_id = $task_id;

    $this->session_id = $session_id;
    $this->num_runnables = $num_runnables_estimate;
    $this->num_runnables_delta = 0;
    $this->has_updates = FALSE;
  }

  public function getNumRunners() {
    return count($this->runner_ids);
  }

  public function getRunnerIds() {
    return $this->runner_ids;
  }

  public function getOwnerSession() {
    return $this->session_id;
  }

  public function getTaskId() {
    return $this->task_id;
  }

  public function getNumRunnables() {
    return $this->num_runnables;
  }

  public function updateNumRunnables($delta) {
    $this->num_runnables += $delta;
    $this->num_runnables_delta += $delta;
    $this->has_updates = TRUE;
  }

  public function getUpdate_NumRunnables() {
    return $this->num_runnables_delta;
  }

  public function hasUpdates() {
    return $this->has_updates;
  }

}
