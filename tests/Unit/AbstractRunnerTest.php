<?php


namespace mbaynton\BatchFramework\Tests\Unit;


use GuzzleHttp\Psr7\Response;
use mbaynton\BatchFramework\TaskInstanceState;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;
use mbaynton\BatchFramework\Tests\Mocks\RunnerControllerMock;
use mbaynton\BatchFramework\Tests\Mocks\RunnerMock;
use mbaynton\BatchFramework\Tests\Mocks\TaskMock;

/**
 * Class AbstractRunnerTest
 *   Tests of all AbstractRunner functionality *except* those related to
 *   measuring the passage of time, computing expected runnable duration,
 *   and deciding how many Runnables to execute per incarnation. Those are in
 *   AbstractRunnerProgressionTest.
 */
class AbstractRunnerTest extends \PHPUnit_Framework_TestCase {
  const TIMESOURCECLASS = '\mbaynton\BatchFramework\Internal\FunctionWrappers';

  public static $monotonic_runner_id = 0;
  public static $monotonic_task_id = 0;

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject $ts
   *   The last FunctionWrappers mock associated to the last sutFactory() call.
   */
  protected $ts;

  /**
   * @param int|null $num_runnables_per_incarnation
   *   If provided, exactly this many Runnables will execute per incarnation,
   *   so for use in tests of things other than the number-of-runnables logic.
   * @param int|null $id
   * @param \mbaynton\BatchFramework\TaskInstanceStateInterface|NULL $scheduledTask
   * @param array $opts
   *  'measured_time': Number of microseconds to report walltime has changed on
   *                   each call to microtime().
   *  'alarm_signal_works': See HttpRunnerControllerTrait::onCreate().
   *  'target_completion_seconds': See HttpRunnerControllerTrait::onCreate().
   *  'controller': An instance of a specific RunnerControllerInterface
   *                implementation to use.
   *
   * @return \mbaynton\BatchFramework\Tests\Mocks\RunnerMock
   */
  protected function sutFactory($num_runnables_per_incarnation = NULL, $id = NULL, TaskInstanceStateInterface $scheduledTask = NULL, $opts = []) {
    if (array_key_exists('controller', $opts)) {
      $controller = $opts['controller'];
    } else {
      $controller = new RunnerControllerMock();
    }

    /**** SET UP TIME SOURCE *****/
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

    /**** END SET UP TIME SOURCE ****/

    if ($id === NULL) {
      $id = self::$monotonic_runner_id++;
    }

    $alarm_signal_works = !empty($opts['alarm_signal_works']);
    $target_seconds = (array_key_exists('target_completion_seconds', $opts) ? $opts['target_completion_seconds'] : 30);

    $sut = new RunnerMock($controller, $ts, $id, $target_seconds, $alarm_signal_works, $num_runnables_per_incarnation);

    if ($scheduledTask !== NULL) {
      $sut->attachScheduledTask($scheduledTask);
    }

    return $sut;
  }

  protected function assignTaskId() {
    return self::$monotonic_task_id++;
  }

  public function testAbstractRunnerInstantiates() {
    $this->sutFactory(1);
  }

  public function testGetIncarnationTargetRuntime() {
    $task = new TaskMock(10, 0);
    $schedule = new TaskInstanceState($task, $this->assignTaskId(), [1], '-');
    $sut = $this->sutFactory(20, 1, $schedule, ['target_completion_seconds' => 42]);

    $this->assertEquals(
      42,
      $sut->getIncarnationTargetRuntime(),
      'getIncarnationTargetRuntime() did not yield the answer to the universe and everything.'
    );
  }

  public function testAbstractRunnerCompletesTask_OneIncarnation() {
    $task = new TaskMock(10, 0);
    $schedule = new TaskInstanceState($task, $this->assignTaskId(), [1], '-');
    $controller = new RunnerControllerMock();
    $sut = $this->sutFactory(20, 1, $schedule, ['controller' => $controller]);
    $result = $sut->run($task, $schedule);
    $this->assertEquals(
      10,
      $result->getBody()->getContents()
    );
    $this->assertEquals(
      1,
      $controller->getNumCalls_onTaskComplete()
    );
  }

  public function testAbstractRunnerCompletesTask_MultipleIncarnations() {
    $task = new TaskMock(30, 0);
    $schedule = new TaskInstanceState($task, $this->assignTaskId(), [1], '-');
    $sut = $this->sutFactory(10, 1, $schedule);
    $num_incarnations = 1;
    while (($result = $sut->run($task, $schedule)) === NULL) {
      $num_incarnations++;
      $sut = $this->sutFactory(10, 1, $schedule);
    }
    $this->assertEquals(
      4,
      $num_incarnations
    );
    $this->assertEquals(
      30,
      $result->getBody()->getContents()
    );
  }

  public function testAbstractRunnerCompletesTask_MultipleIncarnations_2() {
    // Like the above test, but finish the overall Task partway through the
    // last Runner lifecycle.
    $num_runnables = 25;
    $task = new TaskMock($num_runnables, 0);
    $schedule = new TaskInstanceState($task, $this->assignTaskId(), [1], '-');
    $sut = $this->sutFactory(10, 1, $schedule);
    $num_incarnations = 1;
    while (($result = $sut->run($task, $schedule)) === NULL) {
      $num_incarnations++;
      $sut = $this->sutFactory(10, 1, $schedule);
    }
    $this->assertEquals(
      3,
      $num_incarnations
    );
    $this->assertEquals(
      $num_runnables,
      $result->getBody()->getContents()
    );
  }

  public function testAbstractRunnerCompletesTask_MultipleRunners_MultipleIncarnations() {
    $task = new TaskMock(30, 0);
    $this->_multipleRunners_MultipleIncarnations($task);
  }

  public function testAbstractRunnerCompletesNonUnaryTasks_EvenRunnerMultiple() {
    $this->_testAbstractRunnerCompletesNonUnaryTasks(30, TRUE);
  }

  public function testAbstractRunnerCompletesNonUnaryTasks_OddRunnerMultiple() {
    $this->_testAbstractRunnerCompletesNonUnaryTasks(29, TRUE);
  }

  public function testAbstractRunnerCompletesNonreducableTasks_EvenRunnerMultiple() {
    $this->_testAbstractRunnerCompletesNonUnaryTasks(30, FALSE);
  }

  public function testAbstractRunnerCompletesNonreducableTasks_OddRunnerMultiple() {
    $this->_testAbstractRunnerCompletesNonUnaryTasks(29, FALSE);
  }

  protected function _testAbstractRunnerCompletesNonUnaryTasks($num_runners, $supports_reduction) {
    $task = $this->getMockBuilder('\mbaynton\BatchFramework\Tests\Mocks\TaskMock')
      ->setMethods([
        'supportsUnaryPartialResult',
        'assembleResultResponse',
        'supportsReduction'
      ])
      ->enableOriginalConstructor()
      ->setConstructorArgs([$num_runners, 0])
      ->getMock();
    $task->method('supportsUnaryPartialResult')->willReturn(FALSE);
    $task->method('supportsReduction')->willReturn($supports_reduction);
    $task->method('assembleResultResponse')->willReturnCallback(function($final_results) use($supports_reduction) {
      if (! $supports_reduction) {
        $merged_results = [];
        while(($next_result = array_shift($final_results)) !== NULL) {
          $merged_results = array_merge($next_result, $merged_results);
        }
        $final_results = $merged_results;
      }
      return new Response(200, [], array_sum($final_results));
    });

    $this->_multipleRunners_MultipleIncarnations($task);
  }

  protected function _multipleRunners_MultipleIncarnations(TaskInterface $task) {
    // Some arbitrary Runner IDs should not impact anything if in sorted order.
    $runner_ids = [412 + static::$monotonic_runner_id++, 562 + static::$monotonic_runner_id++, 628 + static::$monotonic_runner_id++];
    $schedule = new TaskInstanceState($task, $this->assignTaskId(), $runner_ids, '-');
    foreach ($runner_ids as $runner_id) {
      $runners[$runner_id] = $this->sutFactory(5, $runner_id, $schedule);
    }

    foreach ($runner_ids as $id) {
      $runner_id_incarnations[$id] = 0;
    }
    $result = NULL;
    $incomplete_runner_ids = $runner_ids;
    $controller_informed_task_complete = 0;
    while ($result === NULL) {
      foreach ($incomplete_runner_ids as $runner_id) {
        $runner = $runners[$runner_id];
        $result = $runner->run($task, $schedule);
        $runner_id_incarnations[$runner_id]++;
        $controller_informed_task_complete += $runner->getController()->getNumCalls_onTaskComplete();
        // Make a new Runner with this ID to simulate a new request
        $runners[$runner_id] = $this->sutFactory(5, $runner_id, $schedule);
      }
      $incomplete_runner_ids = $runner->getIncompleteRunnerIds();
    }

    $this->assertEquals(
      $task->getNumRunnables(),
      $result->getBody()->getContents()
    );
    $this->assertEquals(
      array_combine($runner_ids, [3, 3, 3]),
      $runner_id_incarnations
    );
    $this->assertEquals(
      1,
      $controller_informed_task_complete
    );
  }

  public function testSuccessRunnableEvents() {
    $this->_testRunnableEvents(TRUE);
  }

  public function testErrorRunnableEvents() {
    $this->_testRunnableEvents(FALSE);
  }

  protected function _testRunnableEvents($runner_succeeds) {
    $num_runnables = 2;

    $controller = new RunnerControllerMock();

    $task = new TaskMock($num_runnables);
    if (! $runner_succeeds) {
      $task->static_task_callable = function() {
        throw new \Exception('Mocked runnable error.');
      };
    }
    $scheduled_task = new TaskInstanceState($task, $this->assignTaskId(), [1], '-');

    $runner = $this->sutFactory(-1, 1, $scheduled_task, ['controller' => $controller]);

    $runner->run($task, $scheduled_task);

    if ($runner_succeeds) {
      $this->assertEquals(
        $num_runnables,
        $controller->getNumCalls_onBeforeRunnableStarted()
      );

      $this->assertEquals(
        $num_runnables,
        $controller->getNumCalls_onRunnableComplete()
      );

      $this->assertEquals(
        $num_runnables,
        $task->getNumCalls_onRunnableComplete()
      );
    } else {
      $this->assertEquals(
        $num_runnables,
        $controller->getNumCalls_onRunnableError()
      );

      $this->assertEquals(
        $num_runnables,
        $task->getNumCalls_onRunnableError()
      );
    }
  }
}
