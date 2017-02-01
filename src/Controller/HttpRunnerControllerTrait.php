<?php

namespace mbaynton\BatchFramework\Controller;

use mbaynton\BatchFramework\Internal\TimeSource;
use mbaynton\BatchFramework\RunnableInterface;

/**
 * Class HttpRunnerControllerTrait
 *   A trait intended to be used by your Controller class that receives batch-
 *   running worker requests.
 */
trait HttpRunnerControllerTrait {

  /**
   * @var TimeSource $time_source
   */
  protected $time_source;

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
   * Initialization function for the trait's internals. Must be called before
   * using other trait functionality, e.g. in your class constructor.
   *
   * @param TimeSource $time_source
   *   Instance of the TimeSource class.
   * @param bool $alarm_signal_works
   *   Whether pcntl_alarm/pcntl_signal/pcntl_signal_dispatch are available
   *   and functioning on this platform.
   * @param int $target_completion_seconds
   *   Target number of elapsed seconds between dispatching of the first Runnable
   *   and completion of the last Runnable that will be processed during this
   *   HTTP request.
   */
  protected function onCreate(TimeSource $time_source, $alarm_signal_works = FALSE, $target_completion_seconds = 30) {
    $this->alarm_signal_works = $alarm_signal_works;
    $this->alarm_signal_received = FALSE;
    $this->target_completion_seconds = $target_completion_seconds;
    $this->time_source = $time_source;
    $this->last_measured_walltime = $this->time_source->microtime(TRUE);
    $this->start_walltime = $this->last_measured_walltime;
    $this->runnables_since_last_measurement = 0;
    $this->should_continue_running = TRUE;

    if ($this->alarm_signal_works) {
      $this->time_source->pcntl_signal(SIGALRM, function($signo, $siginfo = NULL){
        $this->alarm_signal_received = TRUE;
      });
    }

    // Since we manage a target time to completion ourselves, we don't want the
    // default time limitations fouling it up as long as we stay within reason.
    set_time_limit($this->target_completion_seconds * 2);
  }


  public function onBeforeRunnableStarted(RunnableInterface $runnable) {
    $this->current_runnable = $runnable;
  }

  public function onRunnableComplete(RunnableInterface $runnable, $result) {
    $this->runnableDone($runnable);
  }

  public function onRunnableError(RunnableInterface $runnable, $exception) {
    $this->runnableDone($runnable);
  }

  protected function runnableDone(RunnableInterface $runnable) {
    $this->runnables_since_last_measurement++;
    $this->runnables_since_request_start++;

    if (($new_measured_walltime = $this->shouldRemeasureWalltime()) !== 0.0) {
      if ($new_measured_walltime >= $this->last_measured_walltime) {
        $average_runnable_time = ($new_measured_walltime - $this->last_measured_walltime) / $this->runnables_since_last_measurement;
        $new_runnable_estimate = $this->recordRuntime($average_runnable_time, $this->runnables_since_last_measurement);
        $this->recomputeTotalRunnables($new_measured_walltime, $new_runnable_estimate);

        // As we approach 1 second per runnable, averaging measurements won't
        // provide performance gain.
        if ($average_runnable_time <= 0.75e6) {
          $this->enableTimeAveraging($average_runnable_time);
        } else {
          $this->disableTimeAveraging();
        }
      } else {
        $this->clockDecremented();
      }
      $this->last_measured_walltime = $new_measured_walltime;
      $this->runnables_since_last_measurement = 0;
    }

    if ($this->runnables_since_request_start >= $this->total_runnables_this_request) {
      $this->should_continue_running = FALSE;
    }
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
  }

  public function shouldContinueRunning() {
    return $this->should_continue_running;
  }

}
