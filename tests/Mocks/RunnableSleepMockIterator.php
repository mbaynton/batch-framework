<?php


namespace mbaynton\BatchFramework\Tests\Mocks;


use mbaynton\BatchFramework\AbstractRunnableIterator;

class RunnableSleepMockIterator extends AbstractRunnableIterator {
  /**
   * @var \mbaynton\BatchFramework\Tests\Mocks\TaskSleepMock $task
   */
  protected $task;
  /**
   * @var int $ms_per_runnable
   */
  protected $ms_per_runnable;
  /**
   * @var int $first
   */
  protected $first;
  /**
   * @var RunnableSleepMock $current
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

  public function __construct(TaskSleepMock $task, $first, $increment, $ms_per_runnable) {
    $this->task = $task;
    $this->ms_per_runnable = $ms_per_runnable;
    $this->first = $first;
    $this->increment = $increment;
    $this->last = $task->getNumRunnables() - 1;
    $this->rewind();
  }

  protected function createCurrentRunnable($id) {
    $this->current = new RunnableSleepMock($this->task, $id, $this->ms_per_runnable);
  }

  /**
   * @return RunnableSleepMock
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
