<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-03-02
 * Created Time: 11:49
 */

namespace STS\Bref\Bridge\Lambda\Queue;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\WorkerOptions;
use STS\AwsEvents\Contexts\Context;
use STS\AwsEvents\Events\Event;

class Worker
{
    /**
     * This is the Laravel Event dispatcher, do not confuse
     * it with the Lambda Event router.
     *
     * @var Dispatcher
     */
    private $laravelEventDispatcher;
    /**
     * Our exception handler.
     *
     * @var ExceptionHandler
     */
    private $laravelExceptionhandler;

    /**
     * Create a new Lambda queue worker.
     */
    public function __construct(Dispatcher $laravelEventDispatcher, ExceptionHandler $laravelExceptionhandler)
    {
        $this->laravelEventDispatcher = $laravelEventDispatcher;

        $this->laravelExceptionhandler = $laravelExceptionhandler;
    }

    public function handle(Event $event, Context $context): array
    {
        print_r($event->toArray());
        print_r($context->toArray());
        print_r($_ENV);
        return ['message' => 'All done'];
    }

    /**
     * Determine if the memory limit has been exceeded.
     */
    public function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Process the given job.
     */
    protected function runJob(Job $job, string $connectionName, WorkerOptions $options): void
    {
//        try {
//            return $this->process($connectionName, $job, $options);
//        } catch (Exception $e) {
//            $this->exceptions->report($e);
//
//            $this->stopWorkerIfLostConnection($e);
//        } catch (Throwable $e) {
//            $this->exceptions->report($e = new FatalThrowableError($e));
//
//            $this->stopWorkerIfLostConnection($e);
//        }
    }
//
//    /**
//     * Process the given job from the queue.
//     */
//    public function process(string $connectionName, Job $job, WorkerOptions $options): void
//    {
//        try {
//            // First we will raise the before job event and determine if the job has already ran
//            // over its maximum attempt limits, which could primarily happen when this job is
//            // continually timing out and not actually throwing any exceptions from itself.
//            $this->raiseBeforeJobEvent($connectionName, $job);
//
//            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts(
//                $connectionName,
//                $job,
//                (int) $options->maxTries
//            );
//
//            // Here we will fire off the job and let it process. We will catch any exceptions so
//            // they can be reported to the developers logs, etc. Once the job is finished the
//            // proper events will be fired to let any listeners know this job has finished.
//            $job->fire();
//
//            $this->raiseAfterJobEvent($connectionName, $job);
//        } catch (Exception $e) {
//            $this->handleJobException($connectionName, $job, $options, $e);
//        } catch (Throwable $e) {
//            $this->handleJobException(
//                $connectionName,
//                $job,
//                $options,
//                new FatalThrowableError($e)
//            );
//        }
//    }
//
//    /**
//     * Raise the before queue job event.
//     */
//    protected function raiseBeforeJobEvent(string $connectionName, Job $job): void
//    {
//        $this->events->dispatch(new Events\JobProcessing(
//            $connectionName,
//            $job
//        ));
//    }
//
//    /**
//     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
//     *
//     * This will likely be because the job previously exceeded a timeout.
//     */
//    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts(string $connectionName, Job $job, int $maxTries): void
//    {
//        $maxTries = $job->maxTries() ?? $maxTries;
//
//        $timeoutAt = $job->timeoutAt();
//
//        if ($timeoutAt && Carbon::now()->getTimestamp() <= $timeoutAt) {
//            return;
//        }
//
//        if (! $timeoutAt && ($maxTries === 0 || $job->attempts() <= $maxTries)) {
//            return;
//        }
//
//        $this->failJob($job, $e = new MaxAttemptsExceededException(
//            $job->resolveName() . ' has been attempted too many times or run too long. The job may have previously timed out.'
//        ));
//
//        throw $e;
//    }
//
//    /**
//     * Mark the given job as failed and raise the relevant event.
//     */
//    protected function failJob(Job $job, \Throwable $e): void
//    {
//        return $job->fail($e);
//    }
//
//    /**
//     * Raise the after queue job event.
//     */
//    protected function raiseAfterJobEvent(string $connectionName, Job $job): void
//    {
//        $this->events->dispatch(new Events\JobProcessed(
//            $connectionName,
//            $job
//        ));
//    }
//
//    /**
//     * Handle an exception that occurred while the job was running.
//     */
//    protected function handleJobException(string $connectionName, Job $job, WorkerOptions $options, \Throwable $e): void
//    {
//        try {
//            // First, we will go ahead and mark the job as failed if it will exceed the maximum
//            // attempts it is allowed to run the next time we process it. If so we will just
//            // go ahead and mark it as failed now so we do not have to release this again.
//            if (! $job->hasFailed()) {
//                $this->markJobAsFailedIfWillExceedMaxAttempts(
//                    $connectionName,
//                    $job,
//                    (int) $options->maxTries,
//                    $e
//                );
//            }
//
//            $this->raiseExceptionOccurredJobEvent(
//                $connectionName,
//                $job,
//                $e
//            );
//        } finally {
//            // If we catch an exception, we will attempt to release the job back onto the queue
//            // so it is not lost entirely. This'll let the job be retried at a later time by
//            // another listener (or this same one). We will re-throw this exception after.
//            if (! $job->isDeleted() && ! $job->isReleased() && ! $job->hasFailed()) {
//                $job->release($options->delay);
//            }
//        }
//
//        throw $e;
//    }
//
//    /**
//     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
//     */
//    protected function markJobAsFailedIfWillExceedMaxAttempts(
//        string $connectionName,
//        Job $job,
//        int $maxTries,
//        Throwable $e
//    ): void {
//        $maxTries = $job->maxTries() ?? $maxTries;
//
//        if ($job->timeoutAt() && $job->timeoutAt() <= Carbon::now()->getTimestamp()) {
//            $this->failJob($job, $e);
//        }
//
//        if ($maxTries > 0 && $job->attempts() >= $maxTries) {
//            $this->failJob($job, $e);
//        }
//    }
//
//    /**
//     * Raise the exception occurred queue job event.
//     */
//    protected function raiseExceptionOccurredJobEvent(string $connectionName, Job $job, \Throwable $e): void
//    {
//        $this->events->dispatch(new Events\JobExceptionOccurred(
//            $connectionName,
//            $job,
//            $e
//        ));
//    }
}
