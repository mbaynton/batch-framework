<?php


namespace mbaynton\BatchFramework;


interface TaskSchedulerInterface {
  /**
   * @param \mbaynton\BatchFramework\TaskInterface $task
   * @return ScheduledTaskInterface
   */
  public function scheduleTask(TaskInterface $task);
}
