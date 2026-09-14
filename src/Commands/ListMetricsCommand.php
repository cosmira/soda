<?php

declare(strict_types=1);

namespace Cosmira\Soda\Commands;

use Cosmira\Soda\Analysis\FactCatalog;
use Illuminate\Console\Command;

final class ListMetricsCommand extends Command
{
    /**
     * Optional scope limits the displayed fields.
     */
    protected $signature = 'list:metrics {scope? : file, class or method}';

    /**
     * Explain available expression fields and their collectors.
     */
    protected $description = 'List expression metrics, their meaning and calculation source';

    /**
     * Display the same fact schema consumed by the compiler.
     */
    public function handle(): int
    {
        $scope = $this->argument('scope');
        $facts = FactCatalog::all();
        $isUnknown = $scope !== null && ! isset($facts[$scope]);
        if ($isUnknown) {
            $this->error('Unknown metric scope "'.$scope.'". Available scopes: '.implode(', ', array_keys($facts)).'.');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($facts as $area => $fields) {
            $isOtherScope = $scope !== null && $scope !== $area;
            if ($isOtherScope) {
                continue;
            }

            foreach ($fields as $name => $field) {
                $rows[] = [$area, $name, $field['type'], $field['unit'], $field['description'], $field['source']];
            }
        }

        $this->table(['Scope', 'Field', 'Type', 'Unit', 'Meaning', 'Collector'], $rows);

        return self::SUCCESS;
    }
}
