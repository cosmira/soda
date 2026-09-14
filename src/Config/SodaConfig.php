<?php

declare(strict_types=1);

namespace Cosmira\Soda\Config;

use Cosmira\Soda\Rules\Check;

/**
 * Configuration container for `soda.php`.
 *
 * Start with {@see Soda::configure()} and add rule instances directly:
 *
 * @example
 *   return Soda::configure()
 *       ->withPaths([
 *           'src/',
 *       ])
 *       ->with([
 *           new MaxFileLoc(700),
 *           new MaxMethodLength(100),
 *           new MaxCyclomaticComplexity(10),
 *       ]);
 */
final class SodaConfig
{
    /**
     * @var list<Check>
     */
    private array $checkers = [];

    /**
     * @var list<non-empty-string>
     */
    private array $paths = [];

    /**
     * Register a single rule checker by class name.
     *
     * Prefer {@see with()} with an instance when the rule needs constructor args.
     *
     * @param class-string<Check> $ruleClass
     *
     * @throws \InvalidArgumentException when the class does not exist or does not implement {@see Check}
     */
    public function rule(string $ruleClass): self
    {
        throw_if($ruleClass === '', \InvalidArgumentException::class, 'Rule class name must be non-empty.');
        $classExists = class_exists($ruleClass);

        if (! $classExists) {
            throw new \InvalidArgumentException(sprintf('Rule class not found: %s', $ruleClass));
        }

        $isA = is_a($ruleClass, Check::class, true);

        if (! $isA) {
            throw new \InvalidArgumentException(
                sprintf('%s must implement %s.', $ruleClass, Check::class)
            );
        }

        $this->checkers[] = new $ruleClass;

        return $this;
    }

    /**
     * Register paths analysed when `soda quality` is called without arguments.
     *
     * @param list<non-empty-string> $paths
     */
    public function withPaths(array $paths): self
    {
        foreach ($paths as $path) {
            throw_if(! is_string($path) || $path === '', \InvalidArgumentException::class, 'Each entry in withPaths() must be a non-empty path.');

            $this->paths[] = $path;
        }

        return $this;
    }

    /**
     * Register rule instances directly.
     *
     * This is the primary API. Pass {@see Check} instances. Each rule class carries its own threshold
     * via its constructor — no global config object required.
     *
     * @param array<Check> $rules
     *
     * @throws \InvalidArgumentException when an element is not a Check
     *
     * @example
     *   return Soda::configure()
     *       ->with([
     *           new MaxFileLoc(700),
     *           new MaxCyclomaticComplexity(10),
     *           new UselessVariableRule(),
     *       ]);
     */
    public function with(array $rules): self
    {
        foreach ($rules as $rule) {
            if ($rule instanceof Check) {
                $this->checkers[] = $rule;

                continue;
            }

            throw new \InvalidArgumentException(sprintf(
                'Each entry in with() must extend %s, got %s.',
                Check::class,
                get_debug_type($rule),
            ));
        }

        return $this;
    }

    /**
     * Returns all registered checkers in registration order.
     *
     * @return list<Check>
     */
    public function checks(): array
    {
        return $this->checkers;
    }

    /**
     * @return list<non-empty-string>
     */
    public function paths(): array
    {
        return $this->paths;
    }
}
