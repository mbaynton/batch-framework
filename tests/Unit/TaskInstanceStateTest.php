<?php


namespace mbaynton\BatchFramework\Tests\Unit;


use mbaynton\BatchFramework\TaskInstanceState;
use mbaynton\BatchFramework\Tests\Mocks\TaskMock;

class TaskInstanceStateTest extends \PHPUnit_Framework_TestCase {
  protected function sutFactory($opts) {
    return new TaskInstanceState(
      1,
      isset($opts['num_runners']) ? $opts['num_runners'] : 1,
      isset($opts['num_runnables']) ? $opts['num_runnables'] : 10,
      isset($opts['runner_ids']) ? $opts['runner_ids'] : [1],
      isset($opts['session']) ? $opts['session'] : NULL
    );
  }

  public function testNumRunners() {
    $sut = $this->sutFactory(['num_runners' => 3, 'runner_ids' => [1,2,3]]);

    $this->assertEquals(
      [1,2,3],
      $sut->getRunnerIds()
    );

    $this->assertEquals(
      3,
      $sut->getNumRunners()
    );
  }

  /**
   * @expectedException \InvalidArgumentException
   */
  public function testRunnerIdsValidation() {
    $sut = $this->sutFactory(['num_runners' => 2, 'runner_ids' => [1,2,3]]);
  }

  public function testOwnerSession() {
    $sut = $this->sutFactory(['session' => 'fred']);
    $this->assertEquals(
      'fred',
      $sut->getOwnerSession()
    );

    $sut = $this->sutFactory([]);
    $sut->setOwnerSession('fred23');
    $this->assertEquals(
      'fred23',
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
