<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

final class ClassNameLength extends Check
{
    /**
     * Defines superglobals used by this policy.
     */
    private const array SUPERGLOBALS = [
        'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE',
        '_SESSION', '_REQUEST', '_ENV',
    ];

    /**
     * Set inclusive byte-length bounds for declared names.
     */
    public function __construct(private readonly int $min = 3, private readonly int $max = 32) {}

    /**
     * Use the already parsed syntax tree.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Check the selected category of names from the parsed syntax tree.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, ClassLike::class) as $node) {
            if ($node->name instanceof Identifier) {
                yield from $this->violationsForName($file->path, $node->name->toString(), $node->getLine());
            }
        }
    }

    /**
     * Compare the declared name directly with its inclusive bounds.
     *
     * @return iterable<Violation>
     */
    private function violationsForName(string $file, string $name, int $line): iterable
    {
        $isBuiltinName = $name === 'this' || in_array($name, self::SUPERGLOBALS, true);
        if ($isBuiltinName) {
            return;
        }

        $length = strlen($name);
        $withinBounds = $length >= $this->min && $length <= $this->max;
        if ($withinBounds) {
            return;
        }

        yield new Violation(
            rule: $this->id(),
            file: $file,
            value: $length,
            threshold: $length < $this->min ? $this->min : $this->max,
            line: $line,
            message: sprintf('Name "%s" has length %d.', $name, $length),
        );
    }

    /**
     * Identify this rule in reports.
     */
    public function id(): string
    {
        return 'class_name_length';
    }
}
