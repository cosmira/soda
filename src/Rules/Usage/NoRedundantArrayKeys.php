<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Redundant array keys:',
    advice: 'Remove explicit integer keys when the array already follows the implicit 0, 1, 2 sequence.',
)]
final class NoRedundantArrayKeys extends Check
{
    /**
     * Identify redundant list-key diagnostics in reports.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_redundant_array_keys';
    }

    /**
     * Inspect array literals without requesting metric collectors.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Report one finding per array whose explicit keys can all be omitted.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Array_::class) as $array) {
            if ($this->hasRedundantKeys($array)) {
                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                    line: $array->getStartLine(),
                    message: 'Remove explicit sequential array keys; PHP assigns the same 0, 1, 2 sequence implicitly.',
                );
            }
        }
    }

    /**
     * Unpacking and nonliteral keys prevent proving that implicit indexing preserves the array.
     */
    private function hasRedundantKeys(Array_ $array): bool
    {
        $hasExplicitKeys = false;
        foreach ($array->items as $index => $item) {
            if ($item === null || $item->unpack) {
                return false;
            }

            if ($item->key === null) {
                continue;
            }

            if (! $item->key instanceof Int_ || $item->key->value !== $index) {
                return false;
            }

            $hasExplicitKeys = true;
        }

        return $hasExplicitKeys;
    }
}
