<?php

namespace mbaynton\BatchFramework;


class RunnableResultAggregator implements RunnableResultAggregatorInterface {
  protected $collectedResults;

  public function __construct() {
    $this->collectedResults = [];
  }

  public function collectResult(RunnableInterface $runnable, $result) {
    $this->collectedResults[$runnable->getId()] = $result;
  }

  public function getCollectedResults() {
    return $this->collectedResults;
  }

  public function getNumCollectedResults() {
    return count($this->collectedResults);
  }
}
