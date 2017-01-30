<?php


namespace mbaynton\BatchFramework\Tests\Unit;


use GuzzleHttp\Psr7\Response;
use mbaynton\BatchFramework\ScheduledTask;
use mbaynton\BatchFramework\ScheduledTaskInterface;
use mbaynton\BatchFramework\TaskInterface;
use mbaynton\BatchFramework\Tests\Mocks\RunnerControllerMock;
use mbaynton\BatchFramework\Tests\Mocks\RunnerMock;
use mbaynton\BatchFramework\Tests\Mocks\TaskSleepMock;

class AbstractRunnerTest extends \PHPUnit_Framework_TestCase {
  protected static $monotonic_runner_id = 0;
  protected static $monotonic_task_id = 0;

  protected function sutFactory($num_runnables_per_incarnation, $id = NULL, ScheduledTaskInterface $scheduledTask = NULL) {
    $controller = new RunnerControllerMock($num_runnables_per_incarnation);
    if ($id === NULL) {
      $id = self::$monotonic_runner_id++;
    }
    $sut = new RunnerMock($controller, $id);

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

  public function testAbstractRunnerCompletesTask_OneIncarnation() {
    $task = new TaskSleepMock(10, 0);
    $schedule = new ScheduledTask($task, $this->assignTaskId(), [1], '-');
    $sut = $this->sutFactory(20, 1, $schedule);
    $result = $sut->run();
    $this->assertEquals(
      10,
      $result->getBody()->getContents()
    );
  }

  public function testAbstractRunnerCompletesTask_MultipleIncarnations() {
    $task = new TaskSleepMock(30, 0);
    $schedule = new ScheduledTask($task, $this->assignTaskId(), [1], '-');
    $sut = $this->sutFactory(10, 1, $schedule);
    $num_incarnations = 1;
    while (($result = $sut->run()) === NULL) {
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
    $task = new TaskSleepMock($num_runnables, 0);
    $schedule = new ScheduledTask($task, $this->assignTaskId(), [1], '-');
    $sut = $this->sutFactory(10, 1, $schedule);
    $num_incarnations = 1;
    while (($result = $sut->run()) === NULL) {
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
    $task = new TaskSleepMock(30, 0);
    $this->_multipleRunners_MultipleIncarnations($task);
  }

  public function testAbstractRunnerCompletesNonUnaryTasks() {
    $task = $this->getMockBuilder('\mbaynton\BatchFramework\Tests\Mocks\TaskSleepMock')
      ->setMethods([
        'supportsUnaryPartialResult',
        'assembleResultResponse'
      ])
      ->enableOriginalConstructor()
      ->setConstructorArgs([30, 0])
      ->getMock();
    $task->method('supportsUnaryPartialResult')->willReturn(FALSE);
    $task->method('assembleResultResponse')->willReturnCallback(function($final_results) {
      return new Response(200, [], array_sum($final_results));
    });

    $this->_multipleRunners_MultipleIncarnations($task);
  }

  protected function _multipleRunners_MultipleIncarnations(TaskInterface $task) {
    // Some arbitrary Runner IDs should not impact anything if in sorted order.
    $runner_ids = [412 + static::$monotonic_runner_id++, 562 + static::$monotonic_runner_id++, 628 + static::$monotonic_runner_id++];
    $schedule = new ScheduledTask($task, $this->assignTaskId(), $runner_ids, '-');
    foreach ($runner_ids as $runner_id) {
      $runners[$runner_id] = $this->sutFactory(5, $runner_id, $schedule);
    }

    foreach ($runner_ids as $id) {
      $runner_id_incarnations[$id] = 0;
    }
    $result = NULL;
    while ($result === NULL) {
      $incomplete_runner_ids = reset($runners)->getIncompleteRunnerIds();
      foreach ($incomplete_runner_ids as $runner_id) {
        $result = $runners[$runner_id]->run();
        $runner_id_incarnations[$runner_id]++;
        // Make a new Runner with this ID to simulate a new request
        $runners[$runner_id] = $this->sutFactory(5, $runner_id, $schedule);
      }
    }

    $this->assertEquals(
      $task->getNumRunnables(),
      $result->getBody()->getContents()
    );
    $this->assertEquals(
      array_combine($runner_ids, [3, 3, 3]),
      $runner_id_incarnations
    );
  }


}
