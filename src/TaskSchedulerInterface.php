<?php


namespace mbaynton\BatchFramework;


interface TaskSchedulerInterface {
  /**
   * @param \mbaynton\BatchFramework\TaskInterface $task
   * @return TaskInstanceStateInterface
   */
  public function scheduleTask(TaskInterface $task);
}
