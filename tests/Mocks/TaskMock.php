<?php


namespace mbaynton\BatchFramework\Tests\Mocks;


use GuzzleHttp\Psr7\Response;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;

class TaskMock implements TaskInterface {
  /**
   * @var int $num_on_complete
   */
  protected $num_on_complete = 0;

  /**
   * @var int $num_on_error
   */
  protected $num_on_error = 0;

  /**
   * @var callable $static_task_callable
   */
  public $static_task_callable;

  /**
   * @var int $min_runners
   */
  protected $min_runners;

  /**
   * @var int $max_runners
   */
  protected $max_runners;

  /**
   * TaskMock constructor.
   *
   * @param callable|null $action
   *   A specific thing for each Runner to actually do.
   */
  public function __construct($action = NULL) {
    if (is_callable($action)) {
      $this->static_task_callable = $action;
    } else {
      $this->static_task_callable = function() {};
    }
  }

  public function getRunnableIterator(TaskInstanceStateInterface $instance_state, RunnerInterface $runner, $rank, $last_processed_runnable_id) {
    if ($last_processed_runnable_id === NULL) {
      $next = $rank;
    } else {
      $next = $last_processed_runnable_id + $instance_state->getNumRunners();
    }
    return new RunnableMockIterator($this, $next, $instance_state->getNumRunners(), $instance_state->getNumRunnables());
  }

  public function onRunnableComplete(TaskInstanceStateInterface $instance_state, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {
    // This is a stupid example. Don't gratuitously collect meaningless result data in real code.
    // The more data, the worse the performance.
    $this->num_on_complete++;
    $aggregator->collectResult($runnable, TRUE);
  }

  public function supportsReduction() {
    return TRUE;
  }

  public function reduce(RunnableResultAggregatorInterface $aggregator) {
    return count($aggregator->getCollectedResults());
  }

  public function onRunnableError(TaskInstanceStateInterface $instance_state, RunnableInterface $runnable, $exception, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {
    $this->num_on_error++;
  }

  public function supportsUnaryPartialResult() {
    return TRUE;
  }

  public function updatePartialResult($new, $current = NULL) {
    return $new + (int)$current;
  }

  public function assembleResultResponse($final_results) {
    return new Response(200, [], $final_results);
  }

  public function getNumCalls_onRunnableComplete() {
    return $this->num_on_complete;
  }

  public function getNumCalls_onRunnableError() {
    return $this->num_on_error;
  }
}
