<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality;

use Bunnivo\Soda\Config\SodaConfig;
use Bunnivo\Soda\Quality\Config\QualityConfigRuleState;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Bunnivo\Soda\Quality\RuleCatalog\RuleCatalog;

final readonly class QualityConfig
{
    /**
     * @psalm-var array<string, int|float>
     */
    public array $rules;

    /**
     * @param array<string, int|float>|null $rules           `null` = full defaults from {@see RuleCatalog}; `[]` = empty thresholds.
     * @param list<RuleChecker>             $pluginCheckers  Extra checkers registered via plugins in soda.php.
     * @param bool                          $noBuiltinRules  When true, the composition root skips StandardPlugin and uses only plugin checkers.
     * @param list<non-empty-string>        $paths           Paths configured in soda.php for `soda quality` without arguments.
     */
    public function __construct(
        ?array $rules = null,
        public QualityConfigRuleState $ruleState = new QualityConfigRuleState(),
        public array $pluginCheckers = [],
        public bool $noBuiltinRules = false,
        public array $paths = [],
    ) {
        $this->rules = $rules ?? RuleCatalog::defaultThresholds();
    }

    public function isRuleEnabled(string $ruleId): bool
    {
        return array_key_exists($ruleId, $this->rules);
    }

    /**
     * Loads thresholds from a PHP file that returns a {@see SodaConfig} instance.
     *
     * The file must return a {@see SodaConfig} instance, usually
     * `return Soda::configure()->withPaths([...])->with([...]);`.
     *
     * @psalm-param non-empty-string $path
     *
     * @throws ConfigException
     */
    public static function fromPhpConfiguratorFile(string $path): self
    {
        self::assertReadable($path);

        /** @var mixed $export */
        $export = require $path;

        if ($export instanceof SodaConfig) {
            return new self(
                rules: [],
                pluginCheckers: $export->pluginCheckers(),
                noBuiltinRules: true,
                paths: $export->paths(),
            );
        }

        throw new ConfigException(sprintf(
            'PHP config "%s" must return a %s instance.',
            $path,
            SodaConfig::class,
        ));
    }

    /**
     * @throws ConfigException
     */
    private static function assertReadable(string $path): void
    {
        throw_unless(is_readable($path), ConfigException::class, 'Config file not readable: '.$path);
    }

    public static function default(): self
    {
        return new self;
    }

    /**
     * @psalm-return int|float
     */
    public function getRule(string $key): int|float
    {
        return $this->rules[$key] ?? 0;
    }

    /**
     * @return list<string>
     */
    public function booleanMethodPrefixExceptions(): array
    {
        return $this->ruleExceptions('boolean_methods_without_prefix')['methods'];
    }

    /**
     * @return array{files: list<string>, classes: list<string>, methods: list<string>}
     */
    public function ruleExceptions(string $ruleId): array
    {
        return $this->ruleState->exceptions[$ruleId] ?? self::emptyRuleExceptions();
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleOptions(string $ruleId): array
    {
        return $this->ruleState->options[$ruleId] ?? [];
    }

    /**
     * @return array{files: list<string>, classes: list<string>, methods: list<string>}
     */
    private static function emptyRuleExceptions(): array
    {
        return [
            'files'   => [],
            'classes' => [],
            'methods' => [],
        ];
    }
}
