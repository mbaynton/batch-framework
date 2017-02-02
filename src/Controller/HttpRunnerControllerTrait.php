<?php

namespace mbaynton\BatchFramework\Controller;


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
  protected $ONABORTED_HALT = 1;

  /**
   * @var int ONABORTED_CLEANSTOP
   *   When the connection is aborted, stop the Runner and save progress.
   *
   *   This option minimizes the likelihood that the same Runnable will be
   *   executed more than once, and so is the best option for Tasks whose
   *   Runnables have side-effects like manipulating a database.
   */
  protected $ONABORTED_CLEANSTOP = 2;

  /**
   * @var int ONABORTED_IGNORE
   *   When the connection is aborted, continue as if nothing happened.
   */
  protected $ONABORTED_IGNORE = 4;

  protected $abort_behavior;

  /**
   * Initialization function for the trait's internals. Must be called before
   * using other trait functionality, e.g. in your class constructor.
   *
   * @param int $abort_behavior
   *   One of the ONABORTED_* properties of this trait.
   *
   *   Defines what will happen if the connection is aborted.
   */
  protected function onCreate($abort_behavior = NULL) {
    // Since we manage a target time to completion ourselves, we don't want the
    // default time limitations fouling it up as long as we stay within reason.
    set_time_limit($this->target_completion_seconds * 2);

    if ($abort_behavior === NULL) {
      $abort_behavior = $this->ONABORTED_CLEANSTOP;
    }
    $this->abort_behavior = $abort_behavior;

    if ($abort_behavior > $this->ONABORTED_IGNORE) {
      ignore_user_abort(TRUE);
    }
  }

  public function shouldContinueRunning() {
    if ($this->abort_behavior === $this->ONABORTED_CLEANSTOP && connection_aborted()) {
      return FALSE;
    }
    return TRUE;
  }
}
