<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 13:23
 */

namespace STS\Bref\Bridge\Services;

use STS\Bref\Bridge\Lambda\Contracts\Application as LambdaContract;
use STS\Bref\Bridge\Lambda\Kernel;
use Thread;
use Threaded;
use function file_exists;
use const PTHREADS_INHERIT_NONE;

class LambdaRunner extends Thread
{
    /**
     * Any parameters for the object
     *
     * @var array|mixed[]
     */
    private $params;
    /**
     * Results from the function
     *
     * @var Threaded
     */
    private $store;
    /**
     * Whether the thread has joined or not.
     *
     * @var bool
     */
    private $joined;

    /**
     * @param mixed ...$params
     */
    public function __construct(Threaded $store, ...$params)
    {
        $this->params = $params;
        $this->store = $store;
        $this->joined = false;
    }

    /**
     * Static method to create and start our threads
     *
     * @param mixed ...$params
     */
    public static function call(Threaded $store, ...$params): LambdaRunner
    {
        $thread = new LambdaRunner($store, ...$params);
        if ($thread->start(PTHREADS_INHERIT_NONE)) {
            return $thread;
        }
    }

    /**
     * Causes the calling context to wait for the referenced Thread to finish executing
     *
     * @link http://www.php.net/manual/en/thread.join.php
     */
    public function join(): bool
    {
        // Make certain we do not attempt to join twice.
        if (! $this->joined) {
            $this->joined = parent::join();
        }
        return $this->joined;
    }

    /**
     * Run the function and catch the results.
     * This is where the thread magic happens,
     * anything done here, is completely separate
     * from our main thread.
     **/
    public function run(): void
    {
        /*
         * The following array cast is necessary to prevent implicit coercion to a
         * Volatile object. Without it, accessing $store in the main thread after
         * this thread has been destroyed would lead to RuntimeException of:
         * "pthreads detected an attempt to connect to an object which has already
         * been destroyed in %s:%d"
         *
         * See this StackOverflow post for additional information:
         * https://stackoverflow.com/a/44852650/4530326
         */
        $this->store[] = (array) $this->lambda(...$this->params);
    }

    /**
     * Laravel entry point for Lambda execution.
     *
     * This is the public/index.php or the artisan of our Lambda.
     */
    protected function lambda(string $event, string $context): array
    {
        define('LARAVEL_START', microtime(true));

        if (file_exists('/var/task/laravel/bootstrap/pre-autoload.php')) {
            require_once '/var/task/laravel/bootstrap/pre-autoload.php';
        }

        /*
        |--------------------------------------------------------------------------
        | Register The Auto Loader
        |--------------------------------------------------------------------------
        |
        | Composer provides a convenient, automatically generated class loader
        | for our application. We just need to utilize it! We'll require it
        | into the script here so that we do not have to worry about the
        | loading of any our classes "manually". Feels great to relax.
        |
        */

        /**
         * Why? Because we chose not to inherit it from the parent thread.
         */
        require '/var/task/laravel/vendor/autoload.php';

        $app = require_once '/var/task/laravel/bootstrap/app.php';

        $storagePath = '/tmp/storage';
        $storagePaths = [
            '/app/public',
            '/framework/cache/data',
            '/framework/sessions',
            '/framework/testing',
            '/framework/views',
            '/logs',
        ];

        // Only make the dirs if we have not previously made them
        if (! is_dir($storagePath . end($storagePaths))) {
            reset($storagePaths);
            foreach ($storagePaths as $path) {
                mkdir($storagePath . $path, 0777, true);
            }
        }

        $app->useStoragePath($storagePath);

        /*
        |--------------------------------------------------------------------------
        | Run The Artisan Application
        |--------------------------------------------------------------------------
        |
        | When we run the console application, the current CLI command will be
        | executed in this console and the response sent back to a terminal
        | or another output device for the developers. Here goes nothing!
        |
        */

        // Inject the Lambda Kernel
        $app->singleton(
            LambdaContract::class,
            Kernel::class
        );

        /** @var Kernel $kernel */
        $kernel = $app->make(LambdaContract::class);

        $results = $kernel->handle($event, $context);

        /*
        |--------------------------------------------------------------------------
        | Shutdown The Application
        |--------------------------------------------------------------------------
        |
        | Once Artisan has finished running, we will fire off the shutdown events
        | so that any final work may be done by the application before we shut
        | down the process. This is the last thing to happen to the request.
        |
        */

        $kernel->terminate(0);

        return $results;
    }
}
