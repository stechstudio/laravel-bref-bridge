<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 16:11
 */

namespace STS\Bref\Bridge\Lambda;

use Illuminate\Contracts\Events\Dispatcher;
use STS\AwsEvents\Contexts\Context;
use STS\AwsEvents\Events\Event;
use STS\Bref\Bridge\Events\LambdaStarting;
use STS\Bref\Bridge\Lambda\Contracts\Application as ApplicationContract;
use STS\Bref\Bridge\Lambda\Contracts\Registrar;

class Application implements ApplicationContract
{

    /** @var array */
    protected $output = [];

    /** @var Dispatcher */
    private $events;

    /** @var Registrar */
    private $router;

    public function __construct(Dispatcher $events, Registrar $router)
    {
        $this->events = $events;
        $this->events->dispatch(new LambdaStarting($this));
        $this->router = $router;
    }

    public function output(): array
    {
        return $this->output;
    }


    public function run(string $event, string $context): array
    {
        $this->currentEvent = Event::fromString($event);
        $this->currentContext = Context::fromJson($context);
        $this->output = $this->router->dispatch($this->currentEvent, $this->currentContext);
        return $this->output();
    }
}
