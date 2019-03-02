<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-03-02
 * Created Time: 10:03
 */

namespace STS\Bref\Bridge\Events;

use STS\Bref\Bridge\Lambda\Application as Lambda;

class LambdaRunning
{
    /** @var Lambda */
    public $lambda;

    public function __construct(Lambda $lambda)
    {
        $this->lambda = $lambda;
    }
}
