<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 16:20
 */

namespace STS\Bref\Bridge\Events;

use STS\Bref\Bridge\Lambda\Application as Lambda;

class LambdaStarting
{

    /** @var Lambda */
    public $lambda;

    public function __construct(Lambda $lambda)
    {
        $this->lambda = $lambda;
    }
}
