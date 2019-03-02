<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-03-02
 * Created Time: 10:03
 */

namespace STS\Bref\Bridge\Events;

use STS\Bref\Bridge\Lambda\Contracts\Registrar;

class LambdaRouterDispatched
{
    /** @var Registrar */
    public $router;

    public function __construct(Registrar $router)
    {
        $this->router = $router;
    }
}
