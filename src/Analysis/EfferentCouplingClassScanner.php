<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;

/**
 * Enters/leaves class and trait declarations for Ce tracking.
 *
 * @internal
 */
final readonly class EfferentCouplingClassScanner
{
    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private EfferentCouplingGraph $graph,
        private EfferentCouplingReferences $types,
    ) {}

    /**
     * Enter using the current analysis state.
     */
    public function enter(Class_|Trait_ $node): void
    {
        $isAnonymousClass = $node->getAttribute('parent') instanceof New_;
        if ($isAnonymousClass) {
            $this->captureDeclaredSupertypes($node);

            return;
        }

        $name = $node->namespacedName?->toString();
        $hasNoName = $name === null || $name === '';

        if ($hasNoName) {
            return;
        }

        $this->graph->pushFrame($name, $this->resolveExtends($node));
        $this->captureDeclaredSupertypes($node);
    }

    /**
     * Capture declared supertypes while visiting the syntax tree.
     */
    private function captureDeclaredSupertypes(Class_|Trait_ $node): void
    {
        if ($node instanceof Class_) {
            $this->captureClassExtendsAndInterfaces($node);
        }

        $this->captureTraitAdoptions($node);
    }

    /**
     * Capture class extends and interfaces while visiting the syntax tree.
     */
    private function captureClassExtendsAndInterfaces(Class_ $node): void
    {
        $this->types->recordName($node->extends);

        foreach ($node->implements as $interfaceName) {
            $this->types->recordName($interfaceName);
        }
    }

    /**
     * Capture trait adoptions while visiting the syntax tree.
     */
    private function captureTraitAdoptions(Class_|Trait_ $node): void
    {
        foreach ($node->getTraitUses() as $use) {
            foreach ($use->traits as $traitName) {
                $this->types->recordName($traitName);
            }
        }
    }

    /**
     * @psalm-return non-empty-string|null
     */
    private function resolveExtends(Class_|Trait_ $node): ?string
    {
        $hasNoParent = ! $node instanceof Class_ || ! $node->extends instanceof Name;
        if ($hasNoParent) {
            return null;
        }

        return $this->types->className($node->extends);
    }

    /**
     * Leave using the current analysis state.
     */
    public function leave(Node $node): void
    {
        $isUnsupportedType = ! ($node instanceof Class_) && ! ($node instanceof Trait_);
        if ($isUnsupportedType) {
            return;
        }

        $isAnonymousClass = $node->getAttribute('parent') instanceof New_;

        if ($isAnonymousClass) {
            return;
        }

        $name = $node->namespacedName?->toString();
        $hasNoName = $name === null || $name === '';

        if ($hasNoName) {
            return;
        }

        $this->graph->popFrame();
    }
}
