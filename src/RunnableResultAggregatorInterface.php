<?php


namespace mbaynton\BatchFramework;


interface RunnableResultAggregatorInterface {
  /**
   * @param \mbaynton\BatchFramework\RunnableInterface $runnable
   * @param $result
   * @return mixed
   */
  public function collectResult(RunnableInterface $runnable, $result);

  /**
   * @return mixed[]
   *   Array of collected results, keyed by the Runnable Id that produced each.
   */
  public function getCollectedResults();

  /**
   * @return int
   */
  public function getNumCollectedResults();

}
