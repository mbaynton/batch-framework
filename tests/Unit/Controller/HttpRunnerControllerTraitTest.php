<?php

namespace mbaynton\BatchFramework\Tests\Unit\Controller;


use mbaynton\BatchFramework\Internal\TimeSource;
use mbaynton\BatchFramework\TaskInterface;
use mbaynton\BatchFramework\Tests\Mocks\RunnableMock;
use mbaynton\BatchFramework\Tests\Mocks\TaskMock;

class HttpRunnerControllerTraitTest extends \PHPUnit_Framework_TestCase {

  const TIMESOURCECLASS = '\mbaynton\BatchFramework\Internal\TimeSource';

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject $ts
   *   The last TimeSource mock associated to the last sutFactory() call.
   */
  protected $ts;

  /**
   * @var TaskInterface $task_dummy
   */
  protected static $task_dummy = NULL;

  public function setUp() {
    parent::setUp();

    if (self::$task_dummy == NULL) {
      self::$task_dummy = new TaskMock(4, 0);
    }
  }

  public function testShouldContinueRunningAtBeginning() {
    $sut = new HttpRunnerController(new TimeSource(), FALSE);
    $this->assertTrue($sut->shouldContinueRunning());
  }

  public function testAdjustedSystemClock() {
    $sut = $this->sutFactory([
      'measured_time' => -10e3,
    ]);

    for ($i = 1; $i <= 6; $i++) {
      $runnable = new RunnableMock(self::$task_dummy, $i * 4, 0);
      $sut->onBeforeRunnableStarted($runnable);
      $sut->onRunnableComplete($runnable, $runnable->run());
      if (! $sut->shouldContinueRunning()) {
        break;
      }
    }

    $this->assertEquals(
      5,
      $i,
      'Exactly 5 runnables are scheduled while system clock is decrementing.'
    );

    $this->assertFalse(
      $sut->shouldContinueRunning(),
      'Stop if clock is found to continually decrement.'
    );
  }

  /**
   * @param array $opts
   *  'measured_time': Number of microseconds to report walltime has changed on
   *                   each call to microtime().
   *  'alarm_signal_works': See HttpRunnerControllerTrait::onCreate().
   *  'target_completion_seconds': See HttpRunnerControllerTrait::onCreate().
   *
   * @return HttpRunnerController
   */
  protected function sutFactory($opts = []) {
    $ts = $this->getMockBuilder(self::TIMESOURCECLASS)
      ->setMethods([
        'pcntl_alarm',
        'pcntl_signal',
        'pcntl_signal_dispatch',
        'microtime',
        'peekMicrotime',
        'incrementMicrotime',
        'peekAlarmSecs',
        'triggerAlarm',
      ])
      ->getMock();

    $alarm_secs = NULL;
    $ts->method('pcntl_alarm')->willReturnCallback(function($seconds) use (&$alarm_secs) {
      $alarm_secs = $seconds;
      return TRUE;
    });

    $signals = [];
    $ts->method('pcntl_signal')->willReturnCallback(function($signo, $callback) use (&$signals) {
      $signals[$signo] = $callback;
      return TRUE;
    });

    $ts->method('peekAlarmSecs')->willReturnCallback(function() use (&$alarm_secs) {
      return $alarm_secs;
    });

    $alarm_triggered = FALSE;
    $ts->method('triggerAlarm')->willReturnCallback(function() use (&$alarm_triggered) {
      $alarm_triggered = TRUE;
    });

    $ts->method('pcntl_signal_dispatch')->willReturnCallback(function() use (&$alarm_triggered, &$signals) {
      if ($alarm_triggered && is_callable($signals[SIGALRM])) {
        $signals[SIGALRM](SIGALRM, NULL);
      }
      $alarm_triggered = FALSE;
    });

    $faketime = 10e9;
    $increment = (array_key_exists('measured_time', $opts) ? $opts['measured_time'] : 500000); // .5 sec
    $ts->method('microtime')->willReturnCallback(function() use(&$faketime, $increment) {
      $faketime += $increment;
      return $faketime;
    });
    $ts->method('peekMicrotime')->willReturnCallback(function() use(&$faketime) {
      return $faketime;
    });
    $ts->method('incrementMicrotime')->willReturnCallback(function($increment) use (&$faketime) {
      $faketime += $increment;
    });

    $this->ts = $ts;

    $use_signaling = !empty($opts['alarm_signal_works']);
    $target_seconds = (array_key_exists('target_completion_seconds', $opts) ? $opts['target_completion_seconds'] : 30);
    return new HttpRunnerController($ts, $use_signaling, $target_seconds);
  }

  public function testLongRunnablesDisableTimeAveraging() {
    $sut = $this->sutFactory([
      'measured_time' => 1e6,
      'alarm_signal_works' => TRUE,
    ]);

    $this->ts->expects($this->never())->method('pcntl_alarm');
    $this->ts->expects($this->never())->method('pcntl_signal_dispatch');

    for ($i = 1; $i <= 10; $i++) {
      $runnable = new RunnableMock(self::$task_dummy, $i * 4, 0);
      $sut->onBeforeRunnableStarted($runnable);
      $sut->onRunnableComplete($runnable, $runnable->run());
    }
  }

  public function testShortRunnablesEngageTimeAveraging() {
    $sut = $this->sutFactory([
      'measured_time' => 1000,
      'alarm_signal_works' => TRUE
    ]);

    $this->ts->expects($this->atLeastOnce())->method('pcntl_alarm');
    $this->ts->expects($this->atLeast(5))->method('pcntl_signal_dispatch');

    for ($i = 1; $i <= 10; $i++) {
      $runnable = new RunnableMock(self::$task_dummy, $i * 4, 0);
      $sut->onBeforeRunnableStarted($runnable);
      $sut->onRunnableComplete($runnable, $runnable->run());
    }
  }

  protected function simulateRunning(HttpRunnerController $sut, $increment_callback, $theoretical_max_runnables, $runnable_succeeds = TRUE) {
    $count = 0;
    $last_alarm = $this->ts->peekMicrotime();
    while ($sut->shouldContinueRunning() && $count <= $theoretical_max_runnables) {
      $runnable = new RunnableMock(self::$task_dummy, $count * 4, 0);
      $sut->onBeforeRunnableStarted($runnable);
      $this->ts->incrementMicrotime(
        (is_callable($increment_callback) ? $increment_callback($count) : $increment_callback)
      );
      if ($runnable_succeeds) {
        $sut->onRunnableComplete($runnable, $runnable->run());
      } else {
        $sut->onRunnableError($runnable, new \Exception('Pretend exception as prescribed by tests.'));
      }
      $count++;
      if ($this->ts->peekMicrotime() - $last_alarm >= 1e6) {
        $this->ts->triggerAlarm();
        $last_alarm = $this->ts->peekMicrotime();
      }
    }

    $this->assertLessThanOrEqual(
      $theoretical_max_runnables,
      $count,
      'Runnables were over-scheduled.'
    );

    return $count;
  }

  public function testVariableRuntimes_FastToSlow_AreWellScheduled() {
    $target_seconds = 6;

    foreach ([TRUE, FALSE] as $alarm_signal_works) {
      $sut = $this->sutFactory([
        'measured_time' => 0, // no auto-increment
        'alarm_signal_works' => $alarm_signal_works,
        'target_completion_seconds' => $target_seconds,
      ]);

      // With radically different runtimes and no alarm signal, quite a bit
      // of overrun is permissible.
      $theoretical_target_walltime = $alarm_signal_works
        ? (($target_seconds + 1.2) * 1e6) + $this->ts->peekMicrotime()
        : (($target_seconds + 7) * 1e6) + $this->ts->peekMicrotime();

      $count = $this->simulateRunning($sut,
        function ($count) {
          if ($count < 8) {
            return 5e4; // 20 runnables / sec
          }
          else {
            return 1e6; // 1 runnable / sec
          }
        },
        50
      );

      $this->assertLessThanOrEqual(
        $theoretical_target_walltime,
        $this->ts->peekMicrotime(),
        'Fast then slow runnables result in executing substantially longer than target seconds.'
      );
      $this->assertGreaterThanOrEqual(
        13,
        $count,
        'Fast then slow runnables are under-scheduled within target seconds.'
      );
    }
  }

  public function testVariableRuntimes_SlowToFast_AreWellScheduled() {
    $target_seconds = 20;

    foreach ([TRUE, FALSE] as $alarm_signal_works) {
      $sut = $this->sutFactory([
        'measured_time' => 0, // no auto-increment
        'alarm_signal_works' => $alarm_signal_works,
        'target_completion_seconds' => $target_seconds,
      ]);

      $theoretical_target_walltime = (($target_seconds + 1.2) * 1e6) + $this->ts->peekMicrotime();

      $count = $this->simulateRunning($sut,
        function ($count) {
          if ($count < 8) {
            return 1e6; // 1 runnable / sec
          }
          else {
            return 5e4; // 20 runnables / sec
          }
        },
        250
      );

      $this->assertLessThanOrEqual(
        $theoretical_target_walltime,
        $this->ts->peekMicrotime(),
        'Slow then fast runnables result in executing substantially longer than target seconds.'
      );
      $this->assertGreaterThanOrEqual(
        211, // 85% of the 248 runnables if 20 seconds were perfectly scheduled
        $count,
        'Fast then slow runnables are under-scheduled within target seconds.'
      );
    }
  }

  public function testMicrotimeCallCount() {
    // Given constant-time Runnables, we should be polling gettimeofday less
    // than once per 0.7 seconds.
    $target_seconds = 10;
    $runnable_duration_usecs = 50e3;
    $theoretical_max = ($target_seconds * 1e6) / $runnable_duration_usecs;
    $startup_constant = 5; // Number of extra microtime() calls made during startup.
    $theoretical_max_microtime_calls = ($target_seconds / 0.7) + $startup_constant;

    foreach ([TRUE, FALSE] as $alarm_signal_works) {
      $sut = $this->sutFactory([
        'measured_time' => 0,
        'alarm_signal_works' => $alarm_signal_works,
        'target_completion_seconds' => 10,
      ]);

      $this->ts->expects($this->atLeast(7))->method('microtime');
      $this->ts->expects($this->atMost($theoretical_max_microtime_calls))
        ->method('microtime');

      $count = $this->simulateRunning(
        $sut,
        $runnable_duration_usecs,
        $theoretical_max
      );

      $this->assertLessThanOrEqual(
        $theoretical_max,
        $count,
        'Contant-time runnables are over-scheduled.'
      );
      $this->assertGreaterThanOrEqual(
        $theoretical_max * 0.93,
        $count,
        'Constant-time runnables are under-scheduled.'
      );
    }
  }

  public function testConstantRuntimeRunnablesAreWellScheduled() {
    // Given many constant-time runnables, we ought to be able to run
    // at least 93% of the theoretical maximum within the target walltime.
    $target_seconds = 10;
    $runnable_duration_usecs = 10e4; // 1/10th sec
    $theoretical_max = ($target_seconds * 10e5) / $runnable_duration_usecs;

    foreach ([TRUE, FALSE] as $alarm_signal_works) {
      $sut = $this->sutFactory([
        'measured_time' => 0, // no auto-increment
        'alarm_signal_works' => $alarm_signal_works,
        'target_completion_seconds' => $target_seconds,
      ]);

      $theoretical_target_walltime = $this->ts->peekMicrotime() + ($runnable_duration_usecs * $theoretical_max);

      $count = $this->simulateRunning(
        $sut,
        $runnable_duration_usecs,
        $theoretical_max
      );

      $this->assertLessThanOrEqual(
        $theoretical_target_walltime,
        $this->ts->peekMicrotime(),
        'Constant-time runnables result in executing longer than target seconds.'
      );
      $this->assertGreaterThanOrEqual(
        $theoretical_max * 0.93,
        $count,
        'Constant-time runnables are under-scheduled.'
      );
    }
  }

  public function testLongRunnablesAreWellScheduled_1() {
    // 2 - 7 second runnables do not cause overrun of $target_completion_seconds
    // by more than 3 seconds, and do not leave more than 7 seconds unused.
    $target_seconds = 30;
    $runnable_duration_usecs = 2e6; // 2 seconds (min)
    $theoretical_max = ($target_seconds * 1e6) / $runnable_duration_usecs;

    foreach ([TRUE, FALSE] as $alarm_signal_works) {
      $sut = $this->sutFactory([
        'measured_time' => 0, // no auto-increment
        'alarm_signal_works' => $alarm_signal_works,
        'target_completion_seconds' => $target_seconds,
      ]);

      $max_walltime = $this->ts->peekMicrotime() + (($target_seconds + 3) * 1e6);
      $min_walltime = $this->ts->peekMicrotime() + (($target_seconds - 7) * 1e6);

      $this->simulateRunning($sut,
        function($count) { return (2 + $count % 6) * 1e6; },
        $theoretical_max
      );

      $this->assertGreaterThanOrEqual(
        $min_walltime,
        $this->ts->peekMicrotime(),
        'When controlling long runnables, too much unscheduled time left on the table.'
      );
      $this->assertLessThanOrEqual(
        $max_walltime,
        $this->ts->peekMicrotime(),
        'When controlling long runnables, acceptable overage from target runtime exceeded.'
      );
    }
  }

  public function testFailingRunnablesDoNotAffectScheduler() {
    $target_seconds = 8;

    foreach ([TRUE, FALSE] as $alarm_signal_works) {
      $sut = $this->sutFactory([
        'measured_time' => 1e6,
        'alarm_signal_works' => $alarm_signal_works,
        'target_completion_seconds' => $target_seconds,
      ]);

      $theoretical_target_walltime = (($target_seconds + 1.01) * 1e6) + $this->ts->peekMicrotime();

      $count = $this->simulateRunning(
        $sut,
        0,
        8,
        FALSE
      );

      $this->assertLessThanOrEqual(
        $theoretical_target_walltime,
        $this->ts->peekMicrotime(),
        'Failing runnables result in executing substantially longer than target seconds.'
      );
      $this->assertGreaterThanOrEqual(
        8,
        $count,
        'Failing runnables are under-scheduled within target seconds.'
      );
    }
  }
}
