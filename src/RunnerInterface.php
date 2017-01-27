<?php


namespace mbaynton\BatchFramework;


interface RunnerInterface {
  /**
   * @return int
   */
  function getRunnerId();

  /**
   * Runs all the Runnables that this runner will process.
   *
   * @return void
   */
  function run();
}