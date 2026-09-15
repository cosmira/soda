<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\NativeStreamContract;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use Cosmira\Soda\Rules\ExpressionCheck;

final class MaxArguments extends ExpressionCheck
{
    /**
     * Set the maximum accepted value; non-positive limits preserve the disabled policy.
     *
     * @param list<string> $contracts Exact Class::method signatures imposed by external APIs.
     */
    public function __construct(
        private readonly int $limit,
        private readonly ?MaxPropertiesPerClass $properties = null,
        private readonly array $contracts = [],
    ) {}

    /**
     * Preserve the standard bundle's readonly data constructor policy.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        $nativeContracts = NativeStreamContract::signatures($file->nodes);
        $contracts = array_fill_keys(array_map(static fn (string $name): string => strtolower(ltrim($name, '\\')), $this->contracts), true);
        foreach (parent::checkFile($file) as $violation) {
            $identity = strtolower(ltrim($violation->method ?? '', '\\'));
            $nativeArity = $nativeContracts[$identity] ?? 0;
            if (isset($contracts[$identity]) || $violation->value <= $nativeArity) {
                continue;
            }

            $methods = $file->metrics['methods'];
            $method = $methods[$violation->method] ?? [];
            $fields = $method['readonlyDataFields'] ?? null;
            $isDataShape = $fields !== null && $this->properties?->canContain($fields);
            if ($isDataShape) {
                continue;
            }

            yield $violation;
        }
    }

    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'max_arguments';
    }

    /**
     * Keep the measured field and comparison visible next to this rule's constructor.
     */
    #[\Override]
    protected function expression(): string
    {
        return sprintf('method where args > %d', $this->limit);
    }

    /**
     * Non-positive metric limits disable this threshold check.
     */
    #[\Override]
    protected function isEnabled(): bool
    {
        return $this->limit > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\Override]
    protected function violation(FileFacts $file, array $row): Violation
    {
        return new Violation(
            rule: $this->id(),
            file: $file->path,
            value: (int) $row['args'],
            threshold: $this->limit,
            method: $row['name'],
            class: str_contains((string) $row['name'], '::') ? strstr((string) $row['name'], '::', true) : null,
        );
    }
}
