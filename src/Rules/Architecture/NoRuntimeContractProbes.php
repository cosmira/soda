<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Architecture;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

#[Rule(
    label: 'Runtime contract probes:',
    advice: 'Require an interface or typed value instead of discovering capabilities at runtime.',
)]
final class NoRuntimeContractProbes extends Check
{
    /**
     * Report each matching PHP construct at its original source position.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        $nodes = (new NodeFinder)->find($file->nodes, fn (Node $node): bool => $node instanceof Expr\FuncCall && $node->name instanceof Name
                && in_array(strtolower($node->name->toString()), ['method_exists', 'property_exists', 'is_callable'], true));

        foreach ($nodes as $node) {
            yield new Violation(
                rule: $this->id(),
                file: $file->path,
                value: 1,
                threshold: 0,
                line: $node->getStartLine(),
                message: 'Runtime contract probing hides supported capabilities. Require an explicit interface or value shape.',
            );
        }
    }

    /**
     * Return the stable identifier used in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_runtime_contract_probes';
    }

    /**
     * This check uses the resolved AST directly and needs no extra metrics.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }
}
