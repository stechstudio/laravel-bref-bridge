<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-28
 * Created Time: 13:23
 */

namespace STS\Bref\Bridge\Lambda;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use STS\AwsEvents\Contexts\Context;
use STS\AwsEvents\Events\ApiGatewayProxyRequest;
use STS\AwsEvents\Events\CloudformationCreateRequest;
use STS\AwsEvents\Events\Cloudfront;
use STS\AwsEvents\Events\CloudwatchLogs;
use STS\AwsEvents\Events\CognitoSync;
use STS\AwsEvents\Events\Config;
use STS\AwsEvents\Events\DynamodbUpdate;
use STS\AwsEvents\Events\Event;
use STS\AwsEvents\Events\IotButton;
use STS\AwsEvents\Events\KinesisDataFirehouse;
use STS\AwsEvents\Events\KinesisDataStreams;
use STS\AwsEvents\Events\Lex;
use STS\AwsEvents\Events\S3Delete;
use STS\AwsEvents\Events\S3Put;
use STS\AwsEvents\Events\ScheduledEvent;
use STS\AwsEvents\Events\SesEmailReceiving;
use STS\AwsEvents\Events\Sns;
use STS\AwsEvents\Events\Sqs;
use STS\Bref\Bridge\Exceptions\InvalidEventController;
use STS\Bref\Bridge\Lambda\Contracts\Registrar;
use function call_user_func;
use function get_class;

class Router implements Registrar
{
    /**
     * All of the AWS Events supported by the router.
     *
     * @var array
     */
    public static $awsEvents = [
        CloudwatchLogs::class,
        CognitoSync::class,
        Lex::class,
        ApiGatewayProxyRequest::class,
        CloudformationCreateRequest::class,
        Config::class,
        IotButton::class,
        KinesisDataFirehouse::class,
        ScheduledEvent::class,
        Cloudfront::class,
        S3Delete::class,
        S3Put::class,
        DynamodbUpdate::class,
        KinesisDataStreams::class,
        SesEmailReceiving::class,
        Sqs::class,
        Sns::class,
    ];

    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;
    /**
     * The route collection .
     *
     * @var Collection
     */
    protected $routes;
    /**
     * The aws event we are currently handling.
     *
     * @var Event
     */
    protected $currentEvent;
    /**
     * The context associated with the current event
     *
     * @var Context
     */
    protected $currentContext;


    /**
     * Create a new Router instance.
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
        $this->routes = new Collection;
    }

    /**
     * Dispatch a new event to be routed to the appropriate controller
     */
    public function dispatch(Event $event, Context $context): array
    {
        $this->currentEvent = $event;
        $this->currentContext = $context;

        $awsEvent = get_class($event);

        if (! $this->hasController($awsEvent)) {
            throw new InvalidEventController('No controller registered for ' . $awsEvent);
        }
        return call_user_func($this->routes->get(get_class($event)), $event, $context);
    }

    /**
     * Check if an event (by name) has a controller registered.
     */
    public function hasController(string $eventName): bool
    {
        return $this->routes->has($eventName);
    }

    /**
     * Forget a registered route
     */
    public function forget(string $eventName): Registrar
    {
        $this->routes->forget($eventName);
    }

    /**
     * Registers a controller for a particular event by name.
     */
    public function register(string $eventName, callable $controller): Registrar
    {
        $this->routes->put($eventName, $controller);
        return $this;
    }

    /**
     * Register a series of routes based on a configuration array
     */
    public function registerConfiguredControllers(array $config): Registrar
    {
        foreach ($config as $eventName => $controller) {
            if (is_callable($controller)) {
                $this->register($eventName, $controller);
                continue;
            }
            if (class_exists($controller)) {
                $this->register($eventName, [new $controller, 'handle']);
                continue;
            }
            throw new InvalidEventController(sprintf('[%s] -> [%s] is invalid.', $eventName, $controller));
        }
        return $this;
    }
}
