<?php

declare(strict_types=1);

namespace Cosmira\Soda\Commands;

use Cosmira\Soda\Config\SodaInitFileEmitter;

use function file_put_contents;
use function getcwd;

use Illuminate\Console\Command;

final class InitCommand extends Command
{
    /**
     * Stores signature for this analysis instance.
     */
    protected $signature = 'init';

    /**
     * Stores description for this analysis instance.
     */
    protected $description = 'Create soda.php with default quality rules';

    /**
     * Execute the console command and return its exit status.
     */
    public function handle(): int
    {
        $failure = $this->validateOrFail();
        if ($failure !== null) {
            return $failure;
        }

        $path = getcwd().'/soda.php';
        $content = SodaInitFileEmitter::emit();
        $writeFailed = file_put_contents($path, $content) === false;

        if ($writeFailed) {
            $this->error('Failed to write soda.php');

            return self::FAILURE;
        }

        $this->info('Created soda.php');

        return self::SUCCESS;
    }

    /**
     * Validate the supplied configuration and throw when its contract is violated.
     */
    private function validateOrFail(): ?int
    {
        $cwd = getcwd();
        $hasNoDirectory = $cwd === false || $cwd === '';
        /** @psalm-suppress TypeDoesNotContainType - getcwd() can return '' on edge cases */
        if ($hasNoDirectory) {
            $this->error('Cannot determine current directory');

            return self::FAILURE;
        }

        $isConfigReadable = is_readable($cwd.'/soda.php');

        if ($isConfigReadable) {
            $this->error('soda.php already exists');

            return self::FAILURE;
        }

        return null;
    }
}
