<?php declare(strict_types=1);

use STS\AwsEvents\Contexts\Context;
use STS\AwsEvents\Events\Event;
use STS\AwsEvents\Events\Sqs;
use STS\Bref\Bridge\Lambda\Facades\LambdaRoute;
use STS\Bref\Bridge\Lambda\Queue\Worker;

/*
|--------------------------------------------------------------------------
| Lambda Routes
|--------------------------------------------------------------------------
|
| Here is where you can register lambda routes for your application. These
| routes are loaded by the bref service provider. Now create something great!
|
*/

// EXAMPLE: Basic Event, just echoes the event back
LambdaRoute::register(
    Event::class,
    function (Event $event, Context $context): array {
        return $event->toArray();
    }
);

// Default Queue Handler. Assumes any SQS Events are meant for the Laravel Job Queue
LambdaRoute::register(Sqs::class, Worker::class);
