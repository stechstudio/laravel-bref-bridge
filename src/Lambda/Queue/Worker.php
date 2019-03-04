<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-03-02
 * Created Time: 11:49
 */

namespace STS\Bref\Bridge\Lambda\Queue;

use Aws\Credentials\Credentials;
use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Worker as IlluminateQueWorker;
use Illuminate\Queue\WorkerOptions;

class Worker extends IlluminateQueWorker
{
    /** @var WorkerOptions */
    protected $options;
    /** @var Credentials */
    protected $credentials;
    /**
     * @var Application
     */
    private $app;
    /**
     * @var SqsClient
     */
    private $sqs;

    /**
     * Create a new queue worker.
     */
    public function __construct(
        QueueManager $manager,
        Dispatcher $events,
        ExceptionHandler $exceptions,
        Application $app
    ) {
        parent::__construct($manager, $events, $exceptions);

        $this->app = $app;

        $this->connectionName = 'sqs';
        $this->options = new WorkerOptions(
            config('bref.sqs.jobs.options.delay'),
            config('bref.sqs.jobs.options.memory'),
            config('bref.sqs.jobs.options.timeout'),
            config('bref.sqs.jobs.options.sleep'),
            config('bref.sqs.jobs.options.maxTries'),
            config('bref.sqs.jobs.options.force')
        );

        $this->credentials = new Credentials(
            config('bref.sqs.jobs.credentials.access_key_id'),
            config('bref.sqs.jobs.credentials.secret_access_key')
        );
    }

    /**
     * This is our entry point.
     */
    public function handle(Event $event, Context $context): void
    {
        $payload = $event->get('Records')->first()->get('body');
        preg_match(
            '/arn:aws:sqs:(?P<region>[a-z]{2}-[^-]+-[^:]):(?P<aws_id>\d{12}):(?P<queue>[a-zA-Z0-9-_]+)/',
            $event->get('Records')->first()->get('eventSourceARN'),
            $matches
        );

        $this->sqs = new SqsClient([
            'version' => 'latest',
            'region' => $matches['region'],
            'credentials' => $this->credentials,
        ]);

        $sqsJob = new SqsJob(
            $this->app,
            $sqs,
            $event->get('Records')->first(),
            $this->connectionName,
            $matches['queue']
        );

        $this->runJob($sqsJob, $this->connectionName, $this->options);
    }

    /**
     * We do not use this. If it gets called, throw a hissy fit.
     */
    public function daemon(string $connectionName, string $queue, WorkerOptions $options): void
    {
        throw new \RuntimeException(__METHOD__ . 'is unused in this worker');
    }

    /**
     * Determine if the daemon should process on this iteration.
     * We do not use it, so it is always false.
     */
    protected function daemonShouldRun(WorkerOptions $options, string $connectionName, string $queue): bool
    {
        return false;
    }

    /**
     * Get the next job from the queue connection.
     */
    protected function getNextJob(Queue $connection, string $queue): ?Job
    {
        return null;
    }

    /**
     * Pause the worker for the current loop. But we don't loop, so just return.
     */
    protected function pauseWorker(WorkerOptions $options, int $lastRestart): void
    {
        return;
    }

    /**
     * Process the next job on the queue. But, we don't actually listen to a queue
     */
    public function runNextJob(string $connectionName, string $queue, WorkerOptions $options): void
    {
        return;
    }
}
