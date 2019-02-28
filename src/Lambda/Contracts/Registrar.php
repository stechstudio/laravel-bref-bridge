<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-28
 * Created Time: 13:24
 */

namespace STS\Bref\Bridge\Lambda\Contracts;

use STS\AwsEvents\Contexts\Context;
use STS\AwsEvents\Events\Event;

interface Registrar
{
    /**
     * Registers and event controller.
     */
    public function register(string $event, callable $controller): Registrar;

    /**
     * Takes in a configuration and registers all the event controllers.
     */
    public function registerConfiguredControllers(array $config): Registrar;

    /**
     * Remove an event:controller route from the routes.
     */
    public function forget(string $event): Registrar;

    /**
     * Dispatch an event to it's controller
     */
    public function dispatch(Event $event, Context $context): array;

    /**
     * Determine if an event has a controller.
     */
    public function hasController(string $event): bool;


}
