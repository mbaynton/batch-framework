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

  protected $runnables_left;

  public $runnables_processed_this_incarnation = 0;

  protected $increment_callback = NULL;

  protected $last_alarm = NULL;

  /**
   * RunnerMock constructor.
   * @param \mbaynton\BatchFramework\Controller\RunnerControllerInterface $controller
   * @param \mbaynton\BatchFramework\Internal\FunctionWrappers $time_source
   * @param int $runner_id
   * @param bool $target_completion_seconds
   * @param $alarm_signal_works
   * @param int|null $runnables_per_incarnation
   *   If non-null, this will totally override AbstractRunner's decision-making
   *   about continuing to execute Runnables. Useful in tests that want to
   *   assure a certain number of incarnations are used.
   */
  public function __construct(RunnerControllerInterface $controller, $time_source, $runner_id, $target_completion_seconds, $alarm_signal_works, $runnables_per_incarnation = NULL) {
    $this->runner_id = $runner_id;
    $this->controller = $controller;
    $this->runnables_left = $runnables_per_incarnation;

    if (! array_key_exists($this->runner_id, self::$cache_by_id)) {
      self::$cache_by_id[$this->runner_id] = [];
    }

    parent::__construct($controller, $target_completion_seconds, $alarm_signal_works, $time_source);

    if ($this->time_source instanceof \PHPUnit_Framework_MockObject_MockObject) {
      $this->last_alarm = $this->time_source->peekMicrotime();
    }
  }

  public function setIncrementCallback($increment) {
    if ($this->time_source instanceof \PHPUnit_Framework_MockObject_MockObject) {
      $this->increment_callback = $increment;
    } else {
      throw new \LogicException('Increment callbacks only work on \PHPUnit_Framework_MockObject_MockObject mocked time sources.');
    }
  }

  public function shouldContinueRunning() {
    if ($this->runnables_left !== NULL) {
      return $this->runnables_left != 0;
    } else {
      return parent::shouldContinueRunning();
    }
  }

  protected function runnableDone() {
    if ($this->runnables_left !== NULL) {
      $this->runnables_left--;
    }
    $this->runnables_processed_this_incarnation++;

    if ($this->increment_callback) {
      $callback = $this->increment_callback;
      if (is_callable($callback)) {
        $this->time_source->incrementMicrotime(
          $callback($this->runnables_processed_this_incarnation)
        );
      } else {
        $this->time_source->incrementMicrotime($callback);
      }
    }

    if ($this->last_alarm !== NULL) {
      if ($this->time_source->peekMicrotime() - $this->last_alarm >= 1e6) {
        $this->time_source->triggerAlarm();
        $this->last_alarm = $this->time_source->peekMicrotime();
      }
    }

    return parent::runnableDone();
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
      return self::$task_result_cache_by_task_id[$task_id]['ReducedResults'];
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
