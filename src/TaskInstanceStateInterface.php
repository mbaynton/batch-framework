<?php


namespace mbaynton\BatchFramework;

/**
 * Interface TaskInstanceStateInterface.
 *
 * Models information that may vary from Task to Task based on the precise
 * inputs, but not vary once the task has begun.
 */
interface TaskInstanceStateInterface {
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
   *   The ids of the Runners in ascending sorted order.
   */
  function getRunnerIds();

  /**
   * @return string
   */
  function getOwnerSession();

  /**
   * Finds the total number of Runnables that comprise this Task.
   *
   * @return int
   *   A representation of the total number of Runnables that comprise this
   *   Task. An estimate is satisfactory and preferable if a precise figure
   *   cannot be calculated in constant time; it is used only for concerns such
   *   as computation of estimated percent / time remaining.
   */
  function getNumRunnables(TaskInstanceStateInterface $schedule);

  /**
   * Updates the total estimated number of runnables by the $delta.
   *
   * @param int $delta
   * @return void
   */
  function updateNumRunnables($delta);

  /**
   * @return bool
   *   Returns TRUE if updates to the TaskInstanceState that must be
   *   persisted were made.
   */
  function hasUpdates();

}
