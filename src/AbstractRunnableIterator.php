<?php


namespace mbaynton\BatchFramework;


abstract class AbstractRunnableIterator implements \Iterator {
  /**
   * @return RunnableInterface
   */
  public abstract function current();

  public function key() {
    if ($this->valid()) {
      return $this->current()->getId();
    } else {
      return null;
    }
  }
}
