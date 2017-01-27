<?php


namespace mbaynton\BatchFramework;


interface RunnableInterface {
  function run();

  function getTask();

  /**
   * @return int
   */
  function getId();
}
