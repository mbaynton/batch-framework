<?php


namespace mbaynton\BatchFramework\Tests\Mocks;

use mbaynton\BatchFramework\AbstractRunner;
use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\ScheduledTask;
use mbaynton\BatchFramework\ScheduledTaskInterface;

/**
 * Class RunnerMock
 *   This implements the minimal functionality to fulfill the contracts of
 *   AbstractRunner's abstract methods. It eschews actual persistence for a
 *   static cache, since the PHPUnit test is in fact all in-process.
 */
class RunnerMock extends AbstractRunner {
  protected static $cache_by_id = [];
  protected static $task_result_cache_by_task_id = [];

  protected $runner_id;

  public function __construct(RunnerControllerInterface $controller, $runner_id) {
    $this->runner_id = $runner_id;
    $this->controller = $controller;

    if (! array_key_exists($this->runner_id, self::$cache_by_id)) {
      self::$cache_by_id[$this->runner_id] = [];
    }

    parent::__construct($controller);
  }

  public function attachScheduledTask(ScheduledTaskInterface $scheduledTask) {
    $this->task = $scheduledTask->getTask();
    $this->scheduled_task = $scheduledTask;
    if (! array_key_exists($this->scheduled_task->getTaskId(), self::$task_result_cache_by_task_id)) {
      self::$task_result_cache_by_task_id[$this->scheduled_task->getTaskId()] = [];
    }
  }

  public function getRunnerId() {
    return $this->runner_id;
  }

  protected function retrieveRunnerState() {
    $cache = self::$cache_by_id[$this->runner_id];
    $state = [
      'last_completed_runnable_id' => isset($cache['LastCompletedRunnableId']) ? $cache['LastCompletedRunnableId'] : 0,
      'incomplete_runner_ids' => $this->getIncompleteRunnerIds()
    ];

    if ($this->task->supportsUnaryPartialResult()) {

      $task_id = $this->scheduled_task->getTaskId();
      $runner_id = $this->getRunnerId();
      $state['partial_result'] =
        isset(self::$task_result_cache_by_task_id[$task_id]["$runner_id.PartialResult"])
          ? self::$task_result_cache_by_task_id[$task_id]["$runner_id.PartialResult"]
          : NULL;
    }

    return $state;
  }

  public function getIncompleteRunnerIds() {
    $all = $this->scheduled_task->getRunnerIds();
    $incomplete = [];
    foreach($all as $test_id) {
      if (empty(self::$cache_by_id[$test_id]['done'])) {
        $incomplete[] = $test_id;
      }
    }
    return $incomplete;
  }

  protected function retrieveAllResultData() {
    $results = [];
    $task_id = $this->scheduled_task->getTaskId();
    if ($this->task->supportsUnaryPartialResult()) {
      foreach (self::$task_result_cache_by_task_id[$task_id] as $partial_result) {
        $results[] = $partial_result;
      }
    } else {
      foreach (self::$task_result_cache_by_task_id as $collected_results) {
        array_merge($results, $collected_results);
      }
    }

    return $results;
  }

  protected function finalizeTask(RunnableResultAggregatorInterface $aggregator, $runner_id) {
    unset(self::$task_result_cache_by_task_id[$this->scheduled_task->getTaskId()]);
    unset(self::$cache_by_id[$this->runner_id]);
  }

  protected function finalizeRunner($new_result_data, RunnableInterface $last_processed_runnable = NULL, $runner_id, RunnableResultAggregatorInterface $aggregator = NULL) {
    $task_id = $this->scheduled_task->getTaskId();
    $cache = &self::$task_result_cache_by_task_id[$task_id];

    // Persist non-null $new_result_data
    if ($new_result_data !== NULL) {
      if ($this->task->supportsUnaryPartialResult()) {
        $runner_id = $this->getRunnerId();
        $cache["$runner_id.PartialResult"] = $new_result_data;
      }
      else {
        $cache["ReducedResults"][] = $new_result_data;
      }
    }

    $runner_cache = &self::$cache_by_id[$this->runner_id];
    if ($last_processed_runnable != NULL) {
      $runner_cache["LastCompletedRunnableId"] = $last_processed_runnable->getId();
    } else {
      $runner_cache["done"] = TRUE;
    }
  }
}
