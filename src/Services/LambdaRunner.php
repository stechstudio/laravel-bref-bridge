<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 13:23
 */

namespace STS\Bref\Bridge\Services;

use STS\Bref\Bridge\Models\LambdaResult;
use Thread;
use Threaded;
use function json_encode;

class LambdaRunner extends Thread
{
    /**
     * The function to call
     *
     * @var callable
     */
    private $method;
    /**
     * Any parameters for the object
     *
     * @var array|mixed[]
     */
    private $params;
    /**
     * Results from the function
     *
     * @var LambdaResult
     */
    private $store;
    /**
     * Whether the thread has joined or not.
     *
     * @var bool
     */
    private $joined;

    /**
     * Caller constructor
     *
     * Provide a passthrough to call_user_func_array
     *
     * @param mixed ...$params
     */
    public function __construct(LambdaResult $store, callable $method, ...$params)
    {
        $this->method = $method;
        $this->params = $params;
        $this->store = $store;
        $this->joined = false;
    }

    /**
     * Static method to create our threads
     *
     * @param mixed ...$params
     */
    public static function call(LambdaResult $store, callable $method, ...$params): LambdaRunner
    {
        $thread = new LambdaRunner($store, $method, ...$params);
        if ($thread->start()) {
            return $thread;
        }
    }

    /**
     * Fetch the results.
     */
    public function getStore(): Threaded
    {
        $this->join();
        return $this->store;
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
         * See this StackOverflow post for additional information:
         * https://stackoverflow.com/a/44852650/4530326
         */
        $this->store->setResult((array) ($this->method)(...$this->params));
    }

    /**
     * Ensure we are joined, and then return the result.
     **/
    public function __toString(): string
    {
        return json_encode($this->store->getResult());
    }
}
