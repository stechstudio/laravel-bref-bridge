<?php declare(strict_types=1);

/**
 * Package: laravel-bref-bridge
 * Create Date: 2019-02-27
 * Created Time: 14:26
 */

namespace STS\Bref\Bridge\Lambda\Contracts;

interface Kernel
{
    /**
     * Handle an incoming Lambda Event.
     */
    public function handle(string $event, string $context): array;

    /**
     * Get the output for the last handled event.
     */
    public function output(): array;

    /**
     * Terminate the application.
     */
    public function terminate(int $status): void;
}
