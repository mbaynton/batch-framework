<?php


namespace mbaynton\BatchFramework;


interface RunnerInterface {
  /**
   * @return int
   */
  function getRunnerId();

  /**
   * The amount of time the Runner is targeting to elapse inside run().
   *
   * Actual time taken by run() is likely to deviate +/- by a margin that
   * depends on the Runner and the Task.
   *
   * @return int
   *   Wall-clock seconds this Runner is targeting to elapse inside run().
   */
  function getIncarnationTargetRuntime();

  /**
   * Runs all the Runnables that this runner will process.
   *
   * @param TaskInterface $task
   *   The task to be run.
   * @param TaskInstanceStateInterface $scheduled_task
   *   Scheduling details for the task.
   *
   * @return void
   */
  function run(TaskInterface $task, TaskInstanceStateInterface $task_schedule);
}
