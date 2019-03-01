<?php declare(strict_types=1);

use STS\AwsEvents\Contexts\Context;
use STS\AwsEvents\Events\Event;
use STS\Bref\Bridge\Lambda\Facades\LambdaRoute;

/*
|--------------------------------------------------------------------------
| Lambda Routes
|--------------------------------------------------------------------------
|
| Here is where you can register lambda routes for your application. These
| routes are loaded by the bref service provider. Now create something great!
|
*/

LambdaRoute::register(
    Event::class,
    function (Event $event, Context $context): array {
        return $event->toArray();
    }
);
