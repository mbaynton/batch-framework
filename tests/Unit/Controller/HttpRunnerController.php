<?php

namespace mbaynton\BatchFramework\Tests\Unit\Controller;

use mbaynton\BatchFramework\Controller\HttpRunnerControllerTrait;
use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use mbaynton\BatchFramework\Internal\TimeSource;

/**
 * Class HttpRunnerController
 *   A minimal class to test the functonality of HttpRunnerControllerTrait.
 */
class HttpRunnerController implements RunnerControllerInterface  {
  use HttpRunnerControllerTrait;

  public function __construct(TimeSource $time_source, $alarm_signal_works, $target_completion_seconds = 30) {
    $this->onCreate($time_source, $alarm_signal_works, $target_completion_seconds);
  }
}
