<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 16:12
 */

namespace STS\Bref\Bridge\Lambda\Contracts;


interface Application
{
    /**
     * Run a lambda command.
     */
    public function run(): array;

    /**
     * Get the output from the last event.
     */
    public function output(): array;
}
