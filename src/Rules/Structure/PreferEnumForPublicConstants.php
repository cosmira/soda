<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

final class PreferEnumForPublicConstants extends Check
{
    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'prefer_enum_for_public_constants';
    }

    /**
     * Public visibility and grouped declarations are available directly in the AST.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Count declared constants individually, including implicit public visibility.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder())->findInstanceOf($file->nodes, Class_::class) as $class) {
            $count = 0;

            foreach ($class->getConstants() as $declaration) {
                if ($declaration->isPublic()) {
                    $count += count($declaration->consts);
                }
            }

            if ($count <= 2) {
                continue;
            }

            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: $count,
                threshold: 2,
                class: ($class->namespacedName ?? $class->name)?->toString(),
                line: $class->getStartLine(),
                message: "Class declares {$count} public constants (maximum 2). Use an enum instead.",
            );
        }
    }
}
