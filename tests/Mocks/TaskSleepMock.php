<?php


namespace mbaynton\BatchFramework\Tests\Mocks;


use GuzzleHttp\Psr7\Response;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\TaskInterface;

class TaskSleepMock implements TaskInterface {
  /**
   * @var int $num_runnables
   */
  protected $num_runnables;

  /**
   * @var int $ms_per_runnable
   */
  protected $ms_per_runnable;

  public function __construct($num_runnables, $ms_per_runnable) {
    $this->num_runnables = $num_runnables;
    $this->ms_per_runnable = $ms_per_runnable;
  }

  public function getMaxRunners() {
    return 0;
  }

  public function getRunnableIterator(RunnerInterface $runner, $rank, $total_runners, $last_processed_runnable_id) {
    if ($last_processed_runnable_id == 0) {
      $next = $rank;
    } else {
      $next = $last_processed_runnable_id + $total_runners;
    }
    return new RunnableSleepMockIterator($this, $next, $total_runners, $this->getNumRunnables(), $this->ms_per_runnable);
  }

  public function getNumRunnables() {
    return $this->num_runnables;
  }

  public function onRunnableComplete(RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator) {
    // This is a stupid example. Don't gratuitously collect meaningless result data in real code.
    // The more data, the worse the performance.
    $aggregator->collectResult($runnable, TRUE);
  }

  public function supportsReduction() {
    return TRUE;
  }

  public function reduce(RunnableResultAggregatorInterface $aggregator) {
    return count($aggregator->getCollectedResults());
  }

  public function onRunnableError(RunnableInterface $runnable, $exception) {
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
}
