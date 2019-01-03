<?php


namespace mbaynton\BatchFramework;

use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\Internal\FunctionWrappers;
use Psr\Http\Message\ResponseInterface;

/**
 * Class AbstractRunner
 *   This class contains the core routine for RunnerInterface implementations,
 *   while abstracting operations that require state retention so those details
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
   * @var TaskInstanceStateInterface $instance_state
   */
  protected $instance_state;

  /**
   * @var FunctionWrappers $time_source
   */
  protected $time_source = NULL;

  /**
   * @var bool $alarm_signal_works
   */
  protected $alarm_signal_works;

  /**
   * @var bool $alarm_signal_received;
   */
  protected $alarm_signal_received;

  /**
   * @var float $recent_average
   *   An average of recent runtimes used to determine gettimeofday(2) polling
   *   frequency. Unit is microseconds.
   */
  protected $recent_average;

  /**
   * @var float $runnable_runtime_estimate
   *   Our estimated runtime for the next Runnable. Unit is microseconds.
   */
  protected $runnable_runtime_estimate = NULL;

  /**
   * Target number of elapsed seconds between dispatching of the first Runnable
   * and completion of the last Runnable that will be processed during this
   * HTTP request.
   *
   * @var int $target_completion_seconds
   */
  protected $target_completion_seconds = 30;

  /**
   * @var RunnableInterface $current_runnable
   */
  protected $current_runnable;

  /**
   * @var bool $runnables_are_subsecond
   */
  protected $runnables_are_subsecond = FALSE;

  /**
   * @var float $last_measured_walltime
   */
  protected $last_measured_walltime = NULL;

  /**
   * @var float $start_walltime
   */
  protected $start_walltime = NULL;

  /**
   * @var int $runnables_since_last_measurement
   *   Number of runnables processed since last time measurement was taken.
   */
  protected $runnables_since_last_measurement = 0;

  /**
   * @var int $runnables_since_request_start
   *   Number of runnables processed since the beginning of this HTTP request.
   */
  protected $runnables_since_request_start = 0;

  /**
   * @var int $total_runnables_this_request
   */
  protected $total_runnables_this_request = NULL;

  /**
   * @var int $clock_decrement_count
   */
  protected $clock_decrement_count = 0;

  /**
   * @var bool $should_continue_running
   */
  protected $should_continue_running;

  /**
   * @var ProgressInfo $progress_struct
   */
  protected $progress_struct;


  /**
   * AbstractRunner constructor.
   *
   * @param int $target_completion_seconds
   *   Target number of elapsed seconds between dispatching of the first Runnable
   *   and completion of the last Runnable that will be processed during this
   *   incarnation of the Runnable, for example during this HTTP Request.
   * @param bool $alarm_signal_works
   *   Whether pcntl_alarm/pcntl_signal/pcntl_signal_dispatch are available
   *   and functioning on this platform.
   * @param FunctionWrappers|null $function_wrappers
   *   Internal parameter used for unit testing. Leave NULL.
   *   Instance of a FunctionWrappers, or NULL.
   * @param \mbaynton\BatchFramework\Controller\RunnerControllerInterface $controller
   *   The controller. If not provided at construction time, you must call
   *   setController() before calling run().
   */
  public function __construct(
    $target_completion_seconds = 30,
    $alarm_signal_works = FALSE,
    FunctionWrappers $function_wrappers = NULL,
    RunnerControllerInterface $controller = NULL
  ) {
    $this->controller = $controller;
    $this->alarm_signal_works = $alarm_signal_works;
    $this->alarm_signal_received = FALSE;
    $this->target_completion_seconds = $target_completion_seconds;
    if ($this->time_source === NULL) {
      $this->time_source = FunctionWrappers::get($function_wrappers);
    }
    $this->last_measured_walltime = $this->time_source->microtime(TRUE);
    $this->start_walltime = $this->last_measured_walltime;
    $this->runnables_since_last_measurement = 0;
    $this->should_continue_running = TRUE;

    if ($this->alarm_signal_works) {
      $this->time_source->pcntl_signal(SIGALRM, function($signo, $siginfo = NULL){
        $this->alarm_signal_received = TRUE;
      });
    }
  }

  /**
   * Setter injection of the controller, if it is not convenient to provide it
   * at construction time.
   *
   * @param \mbaynton\BatchFramework\Controller\RunnerControllerInterface $controller
   *   The controller.
   */
  public function setController(RunnerControllerInterface $controller) {
    $this->controller = $controller;
  }

  /**
   * @return int
   */
  public abstract function getRunnerId();

  /**
   * @return array
   *   Associative array with:
   *   - 'last_completed_runnable_id' int|null
   *     The numeric id of the last RunnableInterface processed by the last
   *     incarnation of this Runner. If this Runner has not executed previously,
   *     is NULL.
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
  public abstract function getIncompleteRunnerIds();

  /**
   * A method that is executed after all the Runnables for this incarnation of
   * the Runner have executed. The implementation is responsible for persisting
   * new result data, what the $last_processed_runnable was, whether the
   * Runner is complete, and if the TaskInstanceState has updates, applying
   * them to the latest TaskInstanceState data.
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
   * @param \mbaynton\BatchFramework\TaskInstanceStateInterface $instance_state
   * @param int $runner_id
   * @param \mbaynton\BatchFramework\RunnableResultAggregatorInterface $aggregator
   * @return void
   */
  protected abstract function finalizeRunner($new_result_data, RunnableInterface $last_processed_runnable = NULL, TaskInstanceStateInterface $instance_state, $runner_id, RunnableResultAggregatorInterface $aggregator = NULL);

  /**
   * A method that is executed after all the Runnables for the lifespan of this
   * Runner have executed, and all other Runners have already executed all their
   * Runnables.
   *
   * @param \mbaynton\BatchFramework\RunnableResultAggregatorInterface $aggregator
   * @param int $runner_id
   * @return void
   */
  protected abstract function finalizeTask(RunnableResultAggregatorInterface $aggregator, $runner_id);

  /**
   * @inheritdoc
   */
  public function getIncarnationTargetRuntime() {
    return $this->target_completion_seconds;
  }

  /**
   * @return ResponseInterface|null
   *   Returns a Response containing the overall computed outcome of the batch
   *   on completion of the entire batch. Otherwise, if the entire batch was not
   *   completed by this incarnation of this Runner, returns NULL.
   */
  public function run(TaskInterface $task, TaskInstanceStateInterface $instance_state) {
    $this->instance_state = $instance_state;
    $this->task = $task;

    $state = $this->retrieveRunnerState();

    // RunnableResultAggregator not complex enough for DI at present.
    $aggregator = new RunnableResultAggregator();
    $next_runnable = NULL;

    $runner_rank = array_search($this->getRunnerId(), $this->instance_state->getRunnerIds());
    if ($runner_rank === FALSE) {
      // Client sent us a runner id that is not valid.
      // TODO: perhaps throw a unique exception at next minor so controller can send a corrective control message?
      return null;
    }

    $runnable_iterator = $this->task->getRunnableIterator(
      $this->instance_state,
      $this,
      $runner_rank,
      $state['last_completed_runnable_id']
    );

    $should_continue_running = TRUE;
    while ($should_continue_running && $runnable_iterator->valid()) {
      $next_runnable = $runnable_iterator->current();
      $this->controller->onBeforeRunnableStarted($next_runnable);
      $success = FALSE;
      $result = NULL;
      try {
        $result = $next_runnable->run($task, $instance_state);
        $success = TRUE;
      } catch (\Exception $e) {
        $progress = $this->runnableDone();
        $this->task->onRunnableError($this->instance_state, $next_runnable, $e, $aggregator, $progress);
        $this->controller->onRunnableError($next_runnable, $e, $progress);
      }

      if ($success) {
        $progress = $this->runnableDone();
        $this->task->onRunnableComplete($this->instance_state, $next_runnable, $result, $aggregator, $progress);
        $this->controller->onRunnableComplete($next_runnable, $result, $progress);
      }

      if ($this->shouldContinueRunning()) {
        $runnable_iterator->next();
      } else {
        $should_continue_running = FALSE;
      }
    }

    $incomplete_runner_ids = $state['incomplete_runner_ids'];
    $task_is_complete = $should_continue_running == TRUE && count(array_diff($incomplete_runner_ids, [$this->getRunnerId()])) == 0;

    if ($this->task->supportsReduction()) {
      if ($aggregator->getNumCollectedResults() > 0) {
        $results_this_runner = $this->task->reduce($aggregator);
      } else {
        $results_this_runner = NULL;
      }

      if ($this->task->supportsUnaryPartialResult()) {
        if ($task_is_complete) {
          // Combine all runners' partial results.
          $runner_partials = $this->retrieveAllResultData();
          $current_partial = array_shift($runner_partials);
          while ($new_partial = array_shift($runner_partials)) {
            $current_partial = $this->task->updatePartialResult($new_partial, $current_partial);
          }
        } else {
          $current_partial = $state['partial_result'];
        }

        if ($results_this_runner !== NULL) {
          $results_this_runner = $this->task->updatePartialResult($results_this_runner, $current_partial);
        } else {
          $results_this_runner = $current_partial;
        }
      }
    } else {
      $results_this_runner = $aggregator->getCollectedResults();
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
      $this->controller->onTaskComplete($this->instance_state);
      $this->finalizeTask($aggregator, $this->getRunnerId());
      return $response;
    } else {
      $this->finalizeRunner($results_this_runner, $next_runnable, $this->instance_state, $this->getRunnerId(), $aggregator);
      return NULL;
    }
  }

  /**
   * @return \mbaynton\BatchFramework\Datatype\ProgressInfo
   */
  protected function runnableDone() {
    $this->runnables_since_last_measurement++;
    $this->runnables_since_request_start++;

    if (($new_measured_walltime = $this->shouldRemeasureWalltime()) !== 0.0) {
      if ($new_measured_walltime >= $this->last_measured_walltime) {
        $progress = new ProgressInfo();
        $this->progress_struct = $progress;
        $progress->timeElapsed = $new_measured_walltime - $this->start_walltime;
        $progress->timeElapsedIsEstimated = FALSE;
        $progress->runnablesExecuted = $this->runnables_since_request_start;

        $average_runnable_time = ($new_measured_walltime - $this->last_measured_walltime) / $this->runnables_since_last_measurement;
        $new_runnable_estimate = $this->recordRuntime($average_runnable_time, $this->runnables_since_last_measurement);
        $this->recomputeTotalRunnables($new_measured_walltime, $new_runnable_estimate);
        $progress->estimatedRunnablesRemaining = max($this->total_runnables_this_request - $this->runnables_since_request_start, 0);

        // As we approach 1 second per runnable, averaging measurements won't
        // provide performance gain.
        if ($average_runnable_time <= 0.75e6) {
          $this->enableTimeAveraging($average_runnable_time);
        } else {
          $this->disableTimeAveraging();
        }
      } else {
        $this->clockDecremented();
        $progress = $this->interpolateNewProgress();
      }
      $this->last_measured_walltime = $new_measured_walltime;
      $this->runnables_since_last_measurement = 0;

      // Check in with controller at same interval as we measure actual time
      if (! $this->controller->shouldContinueRunning()) {
        $this->should_continue_running = FALSE;
      }
    } else {
      $progress = $this->interpolateNewProgress();
    }

    if ($this->runnables_since_request_start >= $this->total_runnables_this_request) {
      $this->should_continue_running = FALSE;
    }

    return $progress;
  }

  protected function interpolateNewProgress() {
    $this->progress_struct->estimatedRunnablesRemaining = max($this->total_runnables_this_request - $this->runnables_since_request_start, 0);
    $this->progress_struct->timeElapsedIsEstimated = TRUE;
    $this->progress_struct->timeElapsed += $this->runnable_runtime_estimate;
    $this->progress_struct->runnablesExecuted = $this->runnables_since_request_start;

    return clone $this->progress_struct;
  }

  protected function recordRuntime($average_runnable_time, $num_runnables) {
    if ($this->runnable_runtime_estimate === NULL) {
      $this->runnable_runtime_estimate = $average_runnable_time;
    } else {
      $half = $num_runnables / 2;
      $this->runnable_runtime_estimate = (($this->runnable_runtime_estimate * $half) + ($average_runnable_time * $num_runnables)) / ($num_runnables + $half);
    }
    return $this->runnable_runtime_estimate;
  }

  protected function recomputeTotalRunnables($current_time, $runnable_runtime_estimate) {
    $time_left = $this->target_completion_seconds * 1e6 - ($current_time - $this->start_walltime);
    $runnables_left = max(floor($time_left / $runnable_runtime_estimate), 0);
    $this->total_runnables_this_request = $this->runnables_since_request_start + $runnables_left;
  }

  protected function enableTimeAveraging($recent_average) {
    if ($this->alarm_signal_works) {
      // Flush out old alarms
      $this->time_source->pcntl_signal_dispatch();
      $this->alarm_signal_received = FALSE;
      $this->time_source->pcntl_alarm(1);
    }
    $this->recent_average = $recent_average;
    $this->runnables_are_subsecond = TRUE;
  }

  protected function disableTimeAveraging() {
    if ($this->alarm_signal_works && $this->runnables_are_subsecond) {
      $this->time_source->pcntl_alarm(0);
    }
    $this->runnables_are_subsecond = FALSE;
  }

  /**
   * Called only when average time measurement of several Runnables is engaged,
   * determines whether it is time to check elapsed time again.
   *
   * @return float
   *   New walltime measurement from microtime(TRUE), or 0.
   */
  protected function shouldRemeasureWalltime() {
    if ($this->runnables_are_subsecond === TRUE) {
      if ($this->alarm_signal_works) {
        $this->time_source->pcntl_signal_dispatch();
        // TODO: Don't completely trust PHP to flag all received signals, and
        // eventually call for a remeasure regardless of signal receipt.
        if ($this->alarm_signal_received) {
          $this->alarm_signal_received = FALSE;
          // No need to set another pcntl_alarm(1) here; enableTimeAveraging()
          // will if we still have sub-second runnables.
          return $this->time_source->microtime(TRUE);
        } else {
          return 0.0;
        }
      } else {
        if ($this->runnables_since_request_start <= 5) {
          $new_measurement = $this->time_source->microtime(TRUE);
          $interval = $new_measurement - $this->last_measured_walltime;
          if ($interval > 0) {
            $this->recent_average = ($this->recent_average * ($this->runnables_since_request_start - 1) + $interval) / $this->runnables_since_request_start;
          }
          return $new_measurement;
        }
        else {
          $target_count = max(floor(0.75e6 / $this->recent_average), 1);
          if ($this->runnables_since_last_measurement == $target_count) {
            $new_measurement = $this->time_source->microtime(TRUE);
            $interval = $new_measurement - $this->last_measured_walltime;
            if ($interval > 0) {
              $this->recent_average = $interval / $this->runnables_since_last_measurement;
            }
            return $new_measurement;
          }
          else {
            return 0.0;
          }
        }
      }
    } else { // The Runnables are slow, always measure.
      return $this->time_source->microtime(TRUE);
    }
  }

  protected function clockDecremented() {
    $this->clock_decrement_count++;

    /*
     * If we've caught the clock decrementing 5 times, just stop.
     * This can happen e.g. if ntpd is slewing the sysclock backwards while
     * a batch is running. Much of the dancing around could be avoided if we'd
     * had https://bugs.php.net/bug.php?id=68029 in PHP.
     */
    if ($this->clock_decrement_count >= 5) {
      $this->should_continue_running = FALSE;
    }

    /*
     * If we've been unable to get any valid-looking time readings, no value
     * will be computed for runnables_this_request; allow 5 runnables.
     */
    if ($this->total_runnables_this_request === NULL) {
      $this->total_runnables_this_request = 5;
    }
    if ($this->progress_struct === NULL) {
      $this->progress_struct = new ProgressInfo();
      $this->progress_struct->runnablesExecuted = $this->runnables_since_request_start;
      $this->progress_struct->timeElapsed = 0;
      $this->progress_struct->timeElapsedIsEstimated = TRUE;
      $this->progress_struct->estimatedRunnablesRemaining = max($this->total_runnables_this_request - $this->runnables_since_request_start, 0);
    }
  }

  public function shouldContinueRunning() {
    return $this->should_continue_running;
  }

}
