<?php

declare(strict_types=1);

namespace Cosmira\Soda\Commands;

use Cosmira\Soda\Config\RuleCatalog;
use Illuminate\Console\Command;

final class ListRulesCommand extends Command
{
    /**
     * Stores signature for this analysis instance.
     */
    protected $signature = 'list:rules';

    /**
     * Stores description for this analysis instance.
     */
    protected $description = 'List built-in quality rules';

    /**
     * Execute the console command and return its exit status.
     */
    public function handle(): int
    {
        $rows = [];

        foreach (RuleCatalog::definitions() as $id => $row) {
            $row['id'] = $id;

            $rows[] = [
                $row['id'],
                $row['section'],
                $row['severity'],
                $row['default'],
                $row['label'],
                $row['advice'],
            ];
        }

        $this->table(['Rule id', 'Section', 'Severity', 'Default', 'Label', 'How to improve'], $rows);

        return self::SUCCESS;
    }
}
