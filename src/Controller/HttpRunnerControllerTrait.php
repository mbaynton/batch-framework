<?php

namespace mbaynton\BatchFramework\Controller;
use mbaynton\BatchFramework\Internal\FunctionWrappers;
use mbaynton\BatchFramework\RunnerInterface;


/**
 * Class HttpRunnerControllerTrait
 *   A trait intended to be used by your Controller class that receives batch-
 *   running worker requests.
 */
trait HttpRunnerControllerTrait {

  /**
   * @var int ONABORTED_HALT
   *   When the connection is aborted, let PHP halt immediately.
   *
   *   The safest option for Tasks whose Runnables have no side-effects such
   *   as those only performing computation, or for Runnables that are
   *   idempotent. Although Runnables may be rerun when the client reconnects,
   *   a given Runnable's result is assured of only being reported once.
   */
  public static $ONABORTED_HALT = 1;

  /**
   * @var int ONABORTED_CLEANSTOP
   *   When the connection is aborted, stop the Runner and save progress.
   *
   *   This option minimizes the likelihood that the same Runnable will be
   *   executed more than once, and so is the best option for Tasks whose
   *   Runnables have side-effects like manipulating a database.
   */
  public static $ONABORTED_CLEANSTOP = 2;

  /**
   * @var int ONABORTED_IGNORE
   *   When the connection is aborted, continue as if nothing happened.
   */
  public static $ONABORTED_IGNORE = 4;

  /**
   * @var int $abort_behavior
   *   The selected behavior in event of connection abort.
   */
  protected $abort_behavior;

  /**
   * @var FunctionWrappers $_fnwrap
   */
  protected $_fnwrap = NULL;


  /**
   * Initialization function for the trait's internals. Must be called before
   * using other trait functionality, e.g. in your class constructor.
   *
   * @param int $abort_behavior
   *   One of the ONABORTED_* properties of this trait.
   *   Defines what will happen if the connection is aborted.
   *
   * @param RunnerInterface $runner
   *   The Runner that will be used in servicing of this request.
   */
  protected function onCreate($abort_behavior = NULL, RunnerInterface $runner) {
    // Make sure we have non-null FunctionWrappers.
    $this->_fnwrap = FunctionWrappers::get($this->_fnwrap);

    // Since we manage a target time to completion ourselves, we don't want the
    // default time limitations fouling it up as long as we stay within reason.
    $this->_fnwrap->set_time_limit($runner->getIncarnationTargetRuntime() * 2);

    if ($abort_behavior === NULL) {
      $abort_behavior = self::$ONABORTED_CLEANSTOP;
    }
    $this->abort_behavior = $abort_behavior;

    if ($abort_behavior > self::$ONABORTED_HALT) {
      $this->_fnwrap->ignore_user_abort(TRUE);
    }
  }

  public function shouldContinueRunning() {
    if ($this->abort_behavior === self::$ONABORTED_CLEANSTOP && $this->_fnwrap->connection_aborted()) {
      return FALSE;
    }
    return TRUE;
  }
}
