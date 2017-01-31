<?php


namespace mbaynton\BatchFramework\Tests\Mocks;


use mbaynton\BatchFramework\AbstractRunnableIterator;

class RunnableMockIterator extends AbstractRunnableIterator {
  /**
   * @var \mbaynton\BatchFramework\Tests\Mocks\TaskMock $task
   */
  protected $task;

  /**
   * @var int $first
   */
  protected $first;
  /**
   * @var RunnableMock $current
   */
  protected $current;
  /**
   * @var int $increment
   */
  protected $increment;
  /**
   * @var int $last
   */
  protected $last;

  /**
   * RunnableSleepMockIterator constructor.
   * @param \mbaynton\BatchFramework\Tests\Mocks\TaskMock $task
   * @param int $first
   * @param int $increment
   * @param int $ms_per_runnable
   */
  public function __construct(TaskMock $task, $first, $increment) {
    $this->task = $task;
    $this->first = $first;
    $this->increment = $increment;
    $this->last = $task->getNumRunnables() - 1;
    $this->rewind();
  }

  protected function createCurrentRunnable($id) {
    $this->current = new RunnableMock($this->task, $id);
  }

  /**
   * @return RunnableMock
   */
  public function current() {
    return $this->current;
  }

  public function next() {
    if ($this->valid()) {
      $id = $this->current()->getId() + $this->increment;
      if ($id <= $this->last) {
        $this->createCurrentRunnable($id);
      } else {
        $this->current = NULL;
      }
    }
  }

  public function valid() {
    return $this->current !== NULL;
  }

  public function rewind() {
    if ($this->first <= $this->last) {
      $this->createCurrentRunnable($this->first);
    } else {
      $this->current = NULL;
    }
  }
}
