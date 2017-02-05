<?php

namespace mbaynton\BatchFramework\Internal;

/**
 * Class FunctionWrappers
 *   Provides mockable and observable versions of some PHP built-in functions.
 *
 * @internal
 */
class FunctionWrappers {
  /**
   * @var FunctionWrappers $singleton_instance
   */
  protected static $singleton_instance = NULL;

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

  public function set_time_limit($seconds) {
    return set_time_limit($seconds);
  }

  public function connection_aborted() {
    return connection_aborted();
  }

  public function ignore_user_abort($value) {
    return ignore_user_abort($value);
  }

  public static function singleton() {
    if (self::$singleton_instance === NULL) {
      self::$singleton_instance = new static();
    }
    return self::$singleton_instance;
  }

  /**
   * Convenience method to get some FunctionWrappers implementation.
   * The default one is used unless an alternative is provided.
   *
   * @param \mbaynton\BatchFramework\Internal\FunctionWrappers|NULL $wrappers
   *   An alternative to the default FunctionWrappers, or NULL.
   *
   * @return \mbaynton\BatchFramework\Internal\FunctionWrappers
   */
  public static function get(FunctionWrappers $wrappers = NULL) {
    if ($wrappers !== NULL) {
      return $wrappers;
    } else {
      return self::singleton();
    }
  }
}
