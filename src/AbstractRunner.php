<?php


namespace mbaynton\BatchFramework;

use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class AbstractRunner
 *   This class contains the core routine for RunnerInterface implementations,
 *   while abstracting operations that require state tracking so those details
 *   can be implemented in a manner consistent with the application.
 *
 *   In documentation of abstract methods that direct you to
 *   persist data, you should do so in a location that is a function of
 *   $this->getRunnerId(), and in such a way that the data's lifespan must go
 *   beyond instance variables of this class and be retrievable by any future
 *   instantiation of your class with the same runner id, until finalizeTask()
 *   is called.
 */
abstract class AbstractRunner implements RunnerInterface {
  /**
   * @var RunnerControllerInterface $controller
   */
  protected $controller;

  /**
   * @var TaskInterface $task
   */
  protected $task;

  /**
   * @var ScheduledTask $scheduled_task
   */
  protected $scheduled_task;

  public function __construct(RunnerControllerInterface $controller) {
    $this->controller = $controller;
  }

  /**
   * @return int
   */
  abstract function getRunnerId();

  /**
   * @return array
   *   Associative array with:
   *   - 'last_completed_runnable_id' int
   *     The numeric id of the last RunnableInterface processed by the last
   *     incarnation of this Runner.
   *   - 'incomplete_runner_ids' int[]
   *     The very same results that would be obtained by calling
   *     getIncompleteRunnerIds().
   *
   *   And, Iff $this->task->supportsUnaryPartialResult() returns TRUE,
   *   - 'partial_result' mixed
   *     The last result data provided to finalizeRunner().
   */
  protected abstract function retrieveRunnerState();

  /**
   * Retrieves an array containing values passed to finalizeRunner() as
   * $new_result_data.
   *
   * When $this->task->supportsUnaryPartialResult(), the returned array should
   * be of length <= $this->scheduled_task->getNumRunners(), one for each
   * non-null partial result per Runner.
   *
   * When $this->task->supportsUnaryPartialResult() is false, the returned array
   * should contain every $new_result_data value provided to finalizeRunner()
   * regardless of the originating Runner Id.
   *
   * @return array
   */
  protected abstract function retrieveAllResultData();

  /**
   * On first invocation, contains n unique integers, where n is equal to
   * $this->scheduled_task->getNumRunners(). Each element should be equal to
   * one runner id associated to the processing of the current task.
   *
   * As Runners complete as indicated by calls to finalizeRunner with a null
   * $last_processed_runnable, that id should be removed from the array.
   *
   * @return int[]
   */
  protected abstract function getIncompleteRunnerIds();

  /**
   * A method that is executed after all the Runnables for this incarnation of
   * the Runner have executed. The implementation is responsible for persisting
   * new result data, what the $last_processed_runnable was, and whether the
   * Runner is complete.
   *
   * @param mixed $new_result_data
   *   The result of all Runnables executed in this incarnation of this Runner,
   *   reduced to the extent supported by the task.
   *
   *   Care must be taken to persist $new_result_data properly depending on the
   *   task's support for unary partial results. When
   *   $this->task->supportsUnaryPartialResult() is true, $new_result_data must
   *   be persisted for this task id and runner id, and overwritten if another
   *   value is already present. When $this->task->supportsUnaryPartialResult()
   *   is false, $new_result_data should be added to a set of all such non-null
   *   values being collected for this task id.
   * @param \mbaynton\BatchFramework\RunnableInterface $last_processed_runnable
   *   The last Runnable executed by the run() method. If a non-null value is
   *   provided, persist the return value of its getId() method and retrieve it
   *   in your implementation's getLastCompletedRunnableId().
   * @param int $runner_id
   * @param \mbaynton\BatchFramework\RunnableResultAggregatorInterface $aggregator
   * @return void
   */
  protected abstract function finalizeRunner($new_result_data, RunnableInterface $last_processed_runnable = NULL, $runner_id, RunnableResultAggregatorInterface $aggregator = NULL);

  /**
   * A method that is executed after all the Runnables for the lifespan of this
   * Runner have executed, and all other Runners have already executed all their
   * Runnables.
   *
   * The implementation is responsible for cleanup of state associated to this
   * Task.
   *
   * @param \mbaynton\BatchFramework\RunnableResultAggregatorInterface $aggregator
   * @param \mbaynton\BatchFramework\RunnableInterface $last_processed_runnable
   * @param int $runner_id
   * @return void
   */
  protected abstract function finalizeTask(RunnableResultAggregatorInterface $aggregator, RunnableInterface $last_processed_runnable, $runner_id);

  /**
   * @return ResponseInterface
   *   Returns a Response containing the overall computed outcome of the batch
   *   on completion of the entire batch. Otherwise, if the entire batch was not
   *   completed by this incarnation of this Runner, returns NULL.
   */
  public function run() {
    $state = $this->retrieveRunnerState();

    // RunnableResultAggregator not complex enough for DI at present.
    $aggregator = new RunnableResultAggregator();
    $next_runnable = NULL;

    $runnable_iterator = $this->task->getRunnableIterator(
      $this,
      (empty($state['last_completed_runnable_id']) ? 0 : $state['last_completed_runnable_id'])
    );

    $should_continue_running = TRUE;
    while ($should_continue_running && $runnable_iterator->valid()) {
      $next_runnable = $runnable_iterator->current();
      $this->controller->onBeforeRunnableStarted($next_runnable);
      $success = FALSE;
      $result = NULL;
      try {
        $result = $next_runnable->run();
        $success = TRUE;
      } catch (\Exception $e) {
        $this->task->onRunnableError($next_runnable, $e);
        $this->controller->onRunnableError($next_runnable, $e);
      }

      if ($success) {
        $this->task->onRunnableComplete($next_runnable, $result, $aggregator);
        $this->controller->onRunnableComplete($next_runnable, $result);
      }

      if ($this->controller->shouldContinueRunning()) {
        $runnable_iterator->next();
      } else {
        $should_continue_running = FALSE;
      }
    }

    $incomplete_runner_ids = $state['incomplete_runner_ids'];
    $task_is_complete = $should_continue_running == TRUE && count(array_diff($incomplete_runner_ids, [$this->getRunnerId()])) == 0;

    if ($aggregator->getNumCollectedResults() > 0) {
      $reduction = $this->task->reduce($aggregator);
      if ($reduction !== NULL) {
        if ($this->task->supportsUnaryPartialResult()) {
          if ($task_is_complete) {
            // Combine all runners' partial results and above reduction.
            $runner_partials = $this->retrieveAllResultData();
            $current_partial = array_shift($runner_partials);
            while($new_partial = array_shift($runner_partials)) {
              $current_partial = $this->task->updatePartialResult($new_partial, $current_partial);
            }
            $results_this_runner = $this->task->updatePartialResult($reduction, $current_partial);
          } else {
            $partial_result = $state['partial_result'];
            $results_this_runner = $this->task->updatePartialResult($reduction, $partial_result);
          }
        } else {
          $results_this_runner = $reduction;
        }
      } else {
        $results_this_runner = $aggregator->getCollectedResults();
      }
    } else {
      $results_this_runner = NULL;
    }

    // If we are the last incomplete Runner for the task, and we are done...
    if ($task_is_complete) {
      // If supports unary result, final result is in $results_this_runner.
      // Else, we must ask for the complete set of results now.
      if ($this->task->supportsUnaryPartialResult()) {
        $results = $results_this_runner;
      } else {
        if ($results_this_runner !== NULL) {
          $results = array_merge($this->retrieveAllResultData(), $results_this_runner);
        } else {
          $results = $this->retrieveAllResultData();
        }
      }
      $response = $this->task->assembleResultResponse($results);
      $this->finalizeTask($aggregator, $next_runnable, $this->getRunnerId());
      return $response;
    } else {
      $this->finalizeRunner($results_this_runner, $aggregator, $next_runnable, $this->getRunnerId());
      return NULL;
    }
  }

}
