<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 16:11
 */

namespace STS\Bref\Bridge\Lambda;

use Illuminate\Contracts\Events\Dispatcher;
use STS\Bref\Bridge\Events\LambdaStarting;
use STS\Bref\Bridge\Lambda\Contracts\Application as ApplicationContract;

class Application implements ApplicationContract
{

    /** @var array */
    protected $output = [];
    /** @var Dispatcher */
    private $events;

    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
        $this->events->dispatch(new LambdaStarting($this));
    }

    public function output(): array
    {
        return $this->output;
    }

    public function run(string $event, string $context): array
    {

        return $this->output;
    }
}
