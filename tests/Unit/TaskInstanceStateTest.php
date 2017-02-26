<?php


namespace mbaynton\BatchFramework\Tests\Unit;


use mbaynton\BatchFramework\TaskInstanceState;
use mbaynton\BatchFramework\Tests\Mocks\TaskMock;

class TaskInstanceStateTest extends \PHPUnit_Framework_TestCase {
  protected function sutFactory($opts) {
    $task = new TaskMock(NULL, @$opts['min_runners'], @$opts['max_runners']);

    return new TaskInstanceState(
      $task,
      1,
      [1,2,3,4,5],
      isset($opts['session']) ? $opts['session'] : '-',
      isset($opts['num_runnables']) ? $opts['num_runnables'] : 10
    );
  }

  public function testMaxRunners() {
    $sut = $this->sutFactory(['max_runners' => 3]);

    $this->assertEquals(
      [1,2,3],
      $sut->getRunnerIds()
    );

    $this->assertEquals(
      3,
      $sut->getNumRunners()
    );
  }

  public function testOwnerSession() {
    $sut = $this->sutFactory(['session' => 'fred']);
    $this->assertEquals(
      'fred',
      $sut->getOwnerSession()
    );
  }

  public function testRunnableCountUpdate() {
    $sut = $this->sutFactory(['num_runnables' => 10]);

    $this->assertEquals(
      10,
      $sut->getNumRunnables()
    );

    $this->assertFalse($sut->hasUpdates());

    $sut->updateNumRunnables(5);
    $this->assertEquals(
      15,
      $sut->getNumRunnables()
    );
    $this->assertEquals(
      5,
      $sut->getUpdate_NumRunnables()
    );
    $this->assertTrue($sut->hasUpdates());

    $sut->updateNumRunnables(-1);
    $this->assertEquals(
      14,
      $sut->getNumRunnables()
    );
    $this->assertEquals(
      4,
      $sut->getUpdate_NumRunnables()
    );
    $this->assertTrue($sut->hasUpdates());
  }
}
