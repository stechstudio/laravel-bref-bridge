<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-03-01
 * Created Time: 15:55
 */

namespace STS\Bref\Bridge\Lambda\Facades;

use Illuminate\Support\Facades\Facade;
use STS\Bref\Bridge\Lambda\Contracts\Registrar;

/**
 * @method static array dispatch(Event $event, Context $context)
 * @method static bool hasController(string $eventName)
 * @method static Registrar forget(string $eventName)
 * @method static Registrar register(string $eventName, callable $controller)
 * @method static Registrar registerConfiguredControllers(array $config)
 * @method static void registerFromFile(string $routes)
 */
class LambdaRoute extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bref.lambda.router';
    }
}

