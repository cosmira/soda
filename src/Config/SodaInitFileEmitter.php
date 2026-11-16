<?php

declare(strict_types=1);

namespace Cosmira\Soda\Config;

/** Generates editable configuration from the built-in standard configuration. */
final class SodaInitFileEmitter
{
    /**
     * Copy the explicit rule instances and their constructor arguments.
     */
    public static function emit(): string
    {
        return file_get_contents(__DIR__.'/../../resources/standard.php');
    }
}
