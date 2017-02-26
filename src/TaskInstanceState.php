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
   * @var bool $has_updates
   */
  protected $has_updates;

  /**
   * TaskState constructor.
   *
   * @param TaskInterface $task
   * @param int $task_id
   * @param int[] $runner_ids
   * @param string $session_id
   */
  public function __construct(TaskInterface $task, $task_id, $runner_ids, $session_id) {
    $this->task_id = $task_id;
    if ($task->getMaxRunners($this) > 0) {
      $this->runner_ids = array_splice($runner_ids, 0, min($this->getNumRunners(), $task->getMaxRunners($this)));
    } else {
      $this->runner_ids = $runner_ids;
    }
    $this->session_id = $session_id;
    $this->num_runnables = 0;
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

  public function getNumRunnables(TaskInstanceStateInterface $schedule) {
    return $this->num_runnables;
  }

  public function updateNumRunnables($delta) {
    $this->num_runnables += $delta;
    $this->has_updates = TRUE;
  }

  public function hasUpdates() {
    return $this->has_updates;
  }

  /*
  protected function getSerializedProperties() {
    return ['task', 'task_id', 'runner_ids', 'session_id'];
  }

  public function serialize() {
    $data = [];
    foreach ($this->getSerializedProperties() as $property) {
      $data[$property] = $this->$property;
    }
    return serialize($data);
  }

  public function unserialize($serialized) {
    if (is_string($serialized)) {
      $data = unserialize($serialized);
    } else {
      $data = $serialized;
    }

    foreach ($data as $property => $value) {
      // Set it only if regular serialized property; else a subclass will have
      // to decide what it wants to do.
      if (in_array($property, $this->getSerializedProperties())) {
        $this->$property = $value;
      }
    }
  }
  */
}
