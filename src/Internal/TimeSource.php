<?php

namespace mbaynton\BatchFramework\Internal;

/**
 * Class TimeSource
 *   Provides mockable and observable microtime() and pcntl_* functionality.
 *
 * @internal
 */
class TimeSource {
  public function microtime($get_as_float = TRUE) {
    return microtime($get_as_float);
  }

  public function pcntl_alarm($seconds) {
    return pcntl_alarm($seconds);
  }

  public function pcntl_signal($signo, $handler, $restart_syscalls = TRUE) {
    return pcntl_signal($signo, $handler, $restart_syscalls);
  }

  public function pcntl_signal_dispatch() {
    return pcntl_signal_dispatch();
  }
}
