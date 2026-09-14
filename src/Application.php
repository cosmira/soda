<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner;
use Illuminate\Container\Container;

final class Application extends Container
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct()
    {
        $this->singleton(
            Runner::class,
            static fn (): Runner => new Runner,
        );
    }

    /**
     * Indicate whether the application is running under PHPUnit.
     */
    public function runningUnitTests(): bool
    {
        return ($_ENV['env'] ?? '') === 'testing';
    }
}
