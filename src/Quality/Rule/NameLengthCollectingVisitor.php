<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;

final class NameLengthCollectingVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<NameLengthOccurrence>
     */
    private array $occurrences = [];

    /**
     * @param list<string> $rules
     */
    public function __construct(private readonly array $rules) {}

    #[\Override]
    public function enterNode(Node $node): null
    {
        array_push($this->occurrences, ...$this->occurrencesForNode($node));

        return null;
    }

    /**
     * @return list<NameLengthOccurrence>
     */
    public function occurrences(): array
    {
        return $this->occurrences;
    }

    /**
     * @return list<NameLengthOccurrence>
     */
    private function occurrencesForNode(Node $node): array
    {
        if ($node instanceof Variable && in_array(NameLengthChecker::VARIABLE, $this->rules, true)) {
            return $this->variable($node);
        }

        if ($node instanceof ClassMethod && in_array(NameLengthChecker::METHOD, $this->rules, true)) {
            return [new NameLengthOccurrence(
                NameLengthChecker::METHOD,
                $node->name->toString(),
                $node->getLine(),
            )];
        }

        if ($node instanceof ClassLike && $node->name !== null && in_array(NameLengthChecker::CLASS_LIKE, $this->rules, true)) {
            return [new NameLengthOccurrence(
                NameLengthChecker::CLASS_LIKE,
                $node->name->toString(),
                $node->getLine(),
            )];
        }

        return [];
    }

    /**
     * @return list<NameLengthOccurrence>
     */
    private function variable(Variable $node): array
    {
        return is_string($node->name)
            ? [new NameLengthOccurrence(NameLengthChecker::VARIABLE, $node->name, $node->getLine())]
            : [];
    }
}
