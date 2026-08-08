<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Commands;

use Bunnivo\Soda\Config\SodaInitFileEmitter;
use Bunnivo\Soda\Quality\Config\RuleSections;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Rule\LayerMixingChecker;

use function file_put_contents;
use function getcwd;

use Illuminate\Console\Command;

final class InitCommand extends Command
{
    protected $signature = 'init';

    protected $description = 'Create soda.php with default quality rules';

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildRulesConfig(): array
    {
        $defaults = QualityConfig::default()->rules;
        $overrides = [
            'max_method_length'         => 100,
            'max_class_length'          => 500,
            'max_arguments'             => 16,
            'max_methods_per_class'     => 21,
            'max_file_loc'              => 700,
            'max_line_length'           => 100,
            'max_cyclomatic_complexity' => 26,
            'max_control_nesting'       => 4,
            'min_code_breathing_score'  => 40,
            'variable_name_length'      => 16,
            'method_name_length'        => 32,
            'class_name_length'         => 32,
        ];

        $rules = [
            RuleSections::STRUCTURAL => [],
            RuleSections::COMPLEXITY => [],
            RuleSections::BREATHING  => [],
            RuleSections::NAMING     => [],
        ];

        foreach (RuleSections::ruleToSection() as $ruleKey => $section) {
            $value = $overrides[$ruleKey] ?? $defaults[$ruleKey] ?? null;

            if ($value !== null) {
                $bucket = $rules[$section] ?? [];
                $bucket[$ruleKey] = $ruleKey === LayerMixingChecker::RULE
                    ? [
                        'threshold' => $value,
                        'min_files' => LayerMixingChecker::DEFAULT_MIN_FILES,
                    ]
                    : $value;
                $rules[$section] = $bucket;
            }
        }

        return $rules;
    }

    public function handle(): int
    {
        $failure = $this->validateOrFail();
        if ($failure !== null) {
            return $failure;
        }

        $path = getcwd().'/soda.php';
        $rules = $this->buildRulesConfig();
        $content = SodaInitFileEmitter::emit($rules);

        if (file_put_contents($path, $content) === false) {
            $this->error('Failed to write soda.php');

            return self::FAILURE;
        }

        $this->info('Created soda.php');

        return self::SUCCESS;
    }

    private function validateOrFail(): ?int
    {
        $cwd = getcwd();
        /** @psalm-suppress TypeDoesNotContainType - getcwd() can return '' on edge cases */
        if ($cwd === false || $cwd === '') {
            $this->error('Cannot determine current directory');

            return self::FAILURE;
        }

        if (is_readable($cwd.'/soda.php')) {
            $this->error('soda.php already exists');

            return self::FAILURE;
        }

        return null;
    }
}
