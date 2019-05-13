<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 16:11
 */

namespace STS\Bref\Bridge\Lambda;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use STS\AwsEvents\Contexts\Context;
use STS\AwsEvents\Contracts\Eventful;
use STS\AwsEvents\Events\Event;
use STS\Bref\Bridge\Events\LambdaRunning;
use STS\Bref\Bridge\Events\LambdaStarting;
use STS\Bref\Bridge\Events\LambdaStopping;
use STS\Bref\Bridge\Lambda\Contracts\Application as LambdaContract;
use STS\Bref\Bridge\Lambda\Contracts\Registrar;

class Application implements LambdaContract
{

    /**
     * Stores the results from routing the Lambda Event
     *
     * @var array
     */
    protected $output = [];
    /**
     * The event we are currently working on.
     *
     * @var Event
     */
    protected $currentEvent;
    /**
     * The context for the event we are currently working on.
     *
     * @var Context
     */
    protected $currentContext;
    /**
     * This is the Laravel Event dispatcher, do not confuse
     * it with the Lambda Event router.
     *
     * @var Dispatcher
     */
    private $laravelEventDispatcher;

    /**
     * This is the event router for Lambda events.
     *
     * @var Registrar
     */
    private $lambdaEventRouter;

    public function __construct(Dispatcher $laravelEventDispatcher, Registrar $lambdaEventRouter)
    {
        $this->laravelEventDispatcher = $laravelEventDispatcher;
        $this->laravelEventDispatcher->dispatch(new LambdaStarting($this));
        $this->lambdaEventRouter = $lambdaEventRouter;
    }

    /**
     * Little debug helper until we sort out a Lambda exception Handler.
     */
    protected function logThrowables(\Throwable $t): \Throwable
    {
        Log::debug($t->getMessage());
        Log::debug($t->getTraceAsString());
        return $t;
    }

    /**
     * Returns the Lambda Router results.
     */
    public function output(): array
    {
        return $this->output;
    }

    /**
     * Run the application.
     * Generates the event and context objexts.
     * Then sends them off through the router and returns the results.
     */
    public function run(string $event, string $context): array
    {
        $this->laravelEventDispatcher->dispatch(new LambdaRunning($this));

        try {
            $this->currentEvent = Event::fromString($event);
            app()->instance(Eventful::class, $this->currentEvent);
            app()->alias(Eventful::class, 'bref.lambda.event');
        } catch (\Throwable $t) {
            Log::error('Failed to convert event string to an event object.');
            throw $this->logThrowables($t);
        }

        try {
            $this->currentContext = Context::fromJson($context);
            app()->instance(Context::class, $this->currentContext);
            app()->alias(Context::class, 'bref.lambda.context');
        } catch (\Throwable $t) {
            Log::error('Failed to convert context string to an context object.');
            throw $this->logThrowables($t);
        }

        try {
            $this->output = $this->lambdaEventRouter->dispatch($this->currentEvent, $this->currentContext);
        } catch (\Throwable $t) {
            Log::error('Failed to route the Event.');
            throw $this->logThrowables($t);
        }
        $this->laravelEventDispatcher->dispatch(new LambdaStopping($this));
        return $this->output();
    }
}
