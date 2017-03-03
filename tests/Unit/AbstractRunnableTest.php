<?php


namespace mbaynton\BatchFramework\Tests\Unit;


use mbaynton\BatchFramework\Tests\Mocks\RunnableMock;
use mbaynton\BatchFramework\Tests\Mocks\TaskMock;

class AbstractRunnableTest extends \PHPUnit_Framework_TestCase {
  public function testPropertiesAreGettable() {
    $task = new TaskMock(1, 0);
    $runnable = new RunnableMock($task, 2, 0);

    $this->assertEquals(
      2,
      $runnable->getId()
    );
  }
}
