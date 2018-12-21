<?php


namespace mbaynton\BatchFramework;


use mbaynton\BatchFramework\Datatype\ProgressInfo;
use Psr\Http\Message\ResponseInterface;

interface TaskInterface {

  /**
   * Instantiates the next Runnable that should be processed by the Runner.
   *
   * @param TaskInstanceStateInterface $schedule
   * @param RunnerInterface $runner
   * @paran int $runner_rank
   *   A number between 0 and $num_total_runners - 1.
   * @param int $last_processed_runnable_id
   *   On the first incarnation of each Runner bearing a unique
   *   $runner->getRunnerId(), this value will be null.
   *   The iterator should start with the first Runnable for this Runner.
   *
   *   On subsequent incarnations, this will hold the id of the last Runnable
   *   that has already been processed by the last incarnation of this Runner.
   *   The iterator should start with the next Runnable in sequence for this
   *   Runner.
   *
   *   CAUTION: In the case of a task with a single Runnable, the id will be
   *   0. Strict type checking of this parameter's NULLness is critical.
   * @return AbstractRunnableIterator
   */
  function getRunnableIterator(TaskInstanceStateInterface $schedule, RunnerInterface $runner, $runner_rank, $last_processed_runnable_id);

  function onRunnableComplete(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress);

  function onRunnableError(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $exception, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress);

  /**
   * Whether the Task is able to transform sets of Runnable results into a
   * simpler intermediate result.
   *
   * @return bool
   */
  function supportsReduction();

  /**
   * Transforms aggregated Runnable results into a simpler intermediate result.
   *
   * This is invoked after all the Runnables that will be executed during a
   * given Runner process' lifetime have been run.
   *
   * Task implementations do not need to provide a real implementation of this
   * method, but it can improve performance if collections of Runnable results
   * lend themselves to being simplified. If you do not wish to perform this
   * step, use an empty implementation and return false in supportsReduction().
   *
   * Unlike updatePartialResult(), the type of the return value need not be the
   * same as the types of the input data stored in the $aggregator, and need not
   * be unary.
   *
   * @param \mbaynton\BatchFramework\RunnableResultAggregatorInterface $aggregator
   *   An aggregator instance that has collected one or more result.
   * @return mixed
   *   The returned value is passed on to updatePartialResult() for further
   *   simplification if supported, and then persisted for recall at Task end.
   */
  function reduce(RunnableResultAggregatorInterface $aggregator);

  /**
   * Indicates whether the results of any two reduce() calls can be combined
   * in some way (by calling updatePartialResult()) to produce a new unary
   * result.
   *
   * reduce() performs a similar operation, but on several Runnable results of
   * type R. Note that the type of individual Runnable results, R, is not
   * required to be the same as the return type of reduce(), R'. So, it is
   * necessary to separately declare support for and an operation to perform
   * the combining of R' types.
   *
   * @return bool
   */
  function supportsUnaryPartialResult();

  /**
   * Updates a unary partial result based on new reduce()d Runnable
   * results in case of algorithms whose final result can be progressively
   * computed.
   *
   * For example, if your Task summed many values and each Runnable added two
   * quantities together, you could implement this method to compute the final
   * result by updating a running sum rather than storing each individual
   * Runnable result.
   *
   * Such a facility may be used to minimize the amount of data that must be
   * retained and transferred, thus improving the overall performance of your
   * Task in cases where the outcome of each individual Runnable is not
   * required to assemble the Task's final Response.
   *
   * If your Task does not lend itself to computing a final result
   * progressively, you can provide any no-op implementation and ensure
   * supportsUnaryPartialResult() returns false.
   *
   * @param mixed $new
   *   A result from calling reduce() that was non-null.
   * @param mixed $current
   *   The current value of the partial result.
   *   On first invocation when no partial result yet exists, will be NULL.
   * @return mixed
   *   The new value of the partial result.
   */
  function updatePartialResult($new, $current = NULL);

  /**
   * Invoked after all Runnables have executed and their results reduced and
   * combined, this method is responsible for creating a Response based on the
   * Task's result data.
   *
   * @param mixed $final_results
   *   If supportsUnaryPartialResult() is true, this will be a unary
   *   value arrived at via calls to updatePartialResult().
   *
   *   Otherwise, you will receive an array. If reduce() returns non-null
   *   values, it will contain the results each call to reduce() produced. If
   *   reduce() returns null, it will contain every raw Runnable result.
   *
   * @return ResponseInterface
   */
  function assembleResultResponse($final_results);
}
