<?php


namespace mbaynton\BatchFramework\Tests\Unit;


use mbaynton\BatchFramework\Tests\Mocks\RunnableMockIterator;
use mbaynton\BatchFramework\Tests\Mocks\TaskMock;

class AbstractRunnableIteratorTest extends \PHPUnit_Framework_TestCase {
  public function testKey() {
    $iter = $this->_testKey(1);
    $this->assertFalse($iter->valid());
    $this->assertNull($iter->key());

    $this->_testKey(2);
  }

  protected function _testKey($increment) {
    $task = new TaskMock(5, 0);
    $iter = new RunnableMockIterator($task, 0, $increment, 0);

    $expected = 0;
    while (($iter->valid())) {
      $this->assertEquals(
        $expected,
        $iter->key()
      );
      $iter->next();
      $expected += $increment;
    }
    return $iter;
  }
}
