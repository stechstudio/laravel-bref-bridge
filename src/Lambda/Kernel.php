<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 14:30
 */

namespace STS\Bref\Bridge\Lambda;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Events\Dispatcher;
use STS\Bref\Bridge\Lambda\Application as Lambda;
use STS\Bref\Bridge\Lambda\Contracts\Kernel as KernelContract;

class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var Application
     */
    protected $app;

    /**
     * The event dispatcher implementation.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /** @var Lambda */
    protected $lambda;

    /** @var array */
    protected $output;

    /**
     * Create a new Lambda kernel instance.
     */
    public function __construct(Application $app, Dispatcher $events)
    {
        $this->app = $app;
        $this->events = $events;
    }

    public function handle(string $event, string $context): array
    {
        try {
            $this->bootstrap();
            $this->output = $this->getLambda()->run($event, $context);
        } catch (Throwable $e) {
            $e = new FatalThrowableError($e);
            $this->reportException($e);
            return $this->renderException($e);
        }
        return $this->output;
    }

    /**
     * Bootstrap the application for artisan commands.
     */
    public function bootstrap(): void
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        $this->app->loadDeferredProviders();
    }

    protected function getLambda(): Lambda
    {
        if ($this->lambda === null) {
            return $this->lambda = new Lambda($this->events);
        }
        return $this->lambda;
    }

    /**
     * Report the exception to the exception handler.
     */
    protected function reportException(Exception $e): void
    {
        $this->app[ExceptionHandler::class]->report($e);
    }

    /**
     * Render the given exception for Lambda
     */
    protected function renderException(\Throwable $e): array
    {
        return [
            'exception' => exceptionToArray($e),
            'errorMessage' => $e->getMessage(),
            'errorType' => get_class($e),
        ];
    }

    public function output(): array
    {
        return $this->output;
    }

    /**
     * Terminate the application
     */
    public function terminate(int $status): void
    {
        $this->app->terminate();
    }
}
