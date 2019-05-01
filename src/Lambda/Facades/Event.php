<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-04-30
 * Created Time: 20:52
 */

namespace STS\Bref\Bridge\Lambda\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array toArray()
 * @method static \Tightenco\Collect\Support\Collection toCollection(int $options = 0)
 * @method static int count()
 * @method static string toJson(int $options = 0)
 * @method static string jsonSerialize()
 * @mixin Facade
 */
class Event extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bref.lambda.event';
    }
}
