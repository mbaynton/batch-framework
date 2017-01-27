<?php


namespace mbaynton\BatchFramework;

/**
 * Class ScheduledTaskInterface
 *
 * Wraps a Task with its scheduling information.
 */
interface ScheduledTaskInterface {
  /**
   * @return int
   */
  function getTaskId();

  /**
   * Gets the number of Runners that should work on this Task concurrently.
   *
   * @return int
   */
  function getNumRunners();

  /**
   * Gets the Runner ids performing this task's Runnables.
   *
   * @return int[]
   */
  function getRunnerIds();

  /**
   * @return string
   */
  function getOwnerSession();

  /**
   * @return TaskInterface
   */
  function getTask();
}
