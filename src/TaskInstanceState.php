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
   * @var int $num_runners
   */
  protected $num_runners;

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
   * @param int $num_runnables_estimate
   *   Approximate number of runnables that will be run to complete the task.
   * @param int[]|null $runner_ids
   *   If known at construction time, the set of unique runner id numbers that
   *   have been reserved for runners executing this task. If not provided, the
   *   application must call setRunnerIds before run()ning the task instance.
   *
   *   The array size must equal $num_runners.
   * @param string|null $session_id
   *   The id of the session that is currently running this task instance.
   *   This optional property can help controllers verify that a request should
   *   be used for a runner of this task, but is not used by the framework.
   * @throws \InvalidArgumentException
   *   If $runner_ids is given and not an array of length equal to $num_runners.
   */
  public function __construct($task_id, $num_runners, $num_runnables_estimate, $runner_ids = NULL, $session_id = NULL) {
    $this->task_id = $task_id;

    $this->session_id = $session_id;
    $this->num_runners = $num_runners;
    if ($runner_ids !== NULL) {
      if ($this->_validateRunnerIds($runner_ids)) {
        $this->runner_ids = $runner_ids;
      }
    }
    $this->num_runnables = $num_runnables_estimate;
    $this->num_runnables_delta = 0;
    $this->has_updates = FALSE;
  }

  protected function _validateRunnerIds($runner_ids) {
    if (is_array($runner_ids)) {
      if (count($runner_ids) == $this->num_runners) {
        return TRUE;
      } else {
        throw new \InvalidArgumentException(sprintf('Conflicting arguments: num_runners = %s, but %d runner ids were provided.',
          $this->num_runners,
          count($runner_ids)));
      }
    } else {
      throw new \InvalidArgumentException('$runner_ids must be an array.');
    }
  }

  /**
   * @return int
   */
  public function getNumRunners() {
    return $this->num_runners;
  }

  /**
   * Sets the unique runner id numbers that have been reserved for runners
   * executing this task.
   *
   * If the runner ids were not provided to the constructor, they must be
   * set via this method before the instance is run.
   *
   * @param int[] $runnerIds
   *
   * @throws \InvalidArgumentException
   *   If the size of $runner_ids is not equal to getNumRunners().
   */
  public function setRunnerIds(array $runner_ids) {
    if ($this->_validateRunnerIds($runner_ids)) {
      $this->runner_ids = $runner_ids;
    }
  }

  public function getRunnerIds() {
    return $this->runner_ids;
  }

  public function setOwnerSession($session_id) {
    $this->session_id = $session_id;
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

  protected function getSerializeProperties() {
    $properties = [
      'task_id',
      'runner_ids',
      'session_id',
      'num_runners',
      'num_runnables',
    ];
    return $properties;
  }

  public function serialize() {
    $data = [];
    foreach ($this->getSerializeProperties() as $property) {
      $data[$property] = $this->$property;
    }
    return serialize($data);
  }

  public function unserialize($serialized) {
    $data = unserialize($serialized);
    foreach ($this->getSerializeProperties() as $property) {
      if(array_key_exists($property, $data)) {
        $this->$property = $data[$property];
      }
    }

    $this->num_runnables_delta = 0;
    $this->has_updates = FALSE;
  }

}
