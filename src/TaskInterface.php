<?php


namespace mbaynton\BatchFramework;


use Psr\Http\Message\ResponseInterface;

interface TaskInterface {
  /**1
   * Finds the total number of Runnables that have been added to this Task.
   *
   * @return int
   */
  function getNumRunnables();

  /**
   * Instantiates the next Runnable that should be processed by the Runner.
   *
   * @param RunnerInterface $runner
   * @paran int $runner_rank
   *   A number between 0 and $num_total_runners - 1.
   * @param int $num_total_runners
   *   The total number of Runners executing this task's Runnables.
   * @param int $last_processed_runnable_id
   *   On the first incarnation of each Runner bearing a unique
   *   $runner->getRunnerId(), this value will be 0. The iterator should start
   *   with the first Runnable intended for this Runner.
   *
   *   On subsequent incarnations, this will hold the id of the last Runnable
   *   that has already been processed by the last incarnation of this Runner.
   *   The iterator should start with the next Runnable in sequence for this
   *   Runner.
   * @return AbstractRunnableIterator
   */
  function getRunnableIterator(RunnerInterface $runner, $runner_rank, $num_total_runners, $last_processed_runnable_id);

  function onRunnableComplete(RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator);

  function onRunnableError(RunnableInterface $runnable, $exception);

  /**
   * Transforms aggregated Runnable results into a simpler intermediate result.
   *
   * This is invoked after all the Runnables that will be executed during a
   * given Runner process' lifetime have been run.
   *
   * Task implementations do not need to provide a real implementation of this
   * method, but it can improve performance if collections of Runnable results
   * lend themselves to being simplified. If you do not wish to perform this
   * step, simply return NULL.
   *
   * Unlike updatePartialResult(), the type of the return value need not be the
   * same as the types of the input data stored in the $aggregator.
   *
   * @param \mbaynton\BatchFramework\RunnableResultAggregatorInterface $aggregator
   *   An aggregator instance that has collected one or more result.
   * @return mixed|NULL
   *   If non-null, the returned value is persisted for recall at Task end.
   *   If null, all collected Runnable results are persisted instead.
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
   * Updates a unary partial result based on new, possibly reduce()d Runnable
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
   *   A result from calling reduce() if implemented, else an array of Runner
   *   results.
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
