<?php


namespace mbaynton\BatchFramework\Datatype;

/**
 * Class ProgressInfo
 *   A structure providing information about the progress of a Runner.
 *
 *   What these values are relative to (entire Task, Runnable, Runnable
 *   Incarnation...) depends on their origin.
 */
class ProgressInfo {
  /**
   * @var int $timeElapsed
   *   Microseconds of execution that have elapsed.
   *
   *   WARNING! This value may be an estimate. See $timeElapsedIsEstimated.
   *   As a result, you should be prepared for newer ProgressInfo instances that
   *   have relatively smaller values of $timeElapsedThisIncarnation than older
   *   ProgressInfo instances.
   *
   *   This phenomenon does not apply when comparing two values that were not
   *   estimated, unless the system wall clock is not operating normally.
   */
  public $timeElapsed;

  /**
   * @var bool $timeElapsedIsEstimated
   *   Indicates whether $timeElapsed is an estimate.
   */
  public $timeElapsedIsEstimated;

  /**
   * @var int $runnablesExecuted
   *   The number of Runnables that have been processed.
   */
  public $runnablesExecuted;

  /**
   * @var int $estimatedRunnablesRemaining
   *   The estimated number of Runnables that remain to be executed.
   */
  public $estimatedRunnablesRemaining;
}