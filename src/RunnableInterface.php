<?php


namespace mbaynton\BatchFramework;


interface RunnableInterface {
  function run(TaskInterface $task, TaskInstanceStateInterface $task_state);

  /**
   * @return int
   */
  function getId();
}
