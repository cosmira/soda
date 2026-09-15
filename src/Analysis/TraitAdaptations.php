<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;

/**
 * Resolves public aliases and visibility changes introduced by trait adaptations.
 */
final class TraitAdaptations
{
    /**
     * Preserve aliases of every visibility when computing the effective method count.
     *
     * @return array<string, string>
     */
    public static function methodAliases(Class_|Trait_ $node): array
    {
        $aliases = [];
        foreach ($node->getTraitUses() as $use) {
            foreach ($use->adaptations as $adaptation) {
                if ($adaptation instanceof Alias && $adaptation->newName instanceof Identifier) {
                    $aliases[$adaptation->newName->toLowerString()] = $adaptation->method->toLowerString();
                }
            }
        }

        return $aliases;
    }

    /**
     * @return list<string>
     */
    public static function publicTraitAliasNames(Class_|Trait_ $node): array
    {
        $aliases = [];
        foreach ($node->getTraitUses() as $traitUse) {
            foreach ($traitUse->adaptations as $adaptation) {
                $isAlias = $adaptation instanceof Alias;
                if (! $isAlias) {
                    continue;
                }

                if ($adaptation->newName === null) {
                    continue;
                }

                if (in_array($adaptation->newModifier, [Class_::MODIFIER_PRIVATE, Class_::MODIFIER_PROTECTED], true)) {
                    continue;
                }

                $aliases[] = $adaptation->newName->toString();
            }
        }

        return $aliases;
    }

    /**
     * @return list<string>
     */
    public static function nonPublicTraitMethodNames(Class_|Trait_ $node): array
    {
        $hidden = [];
        foreach ($node->getTraitUses() as $traitUse) {
            foreach ($traitUse->adaptations as $adaptation) {
                $isAlias = $adaptation instanceof Alias;
                if (! $isAlias) {
                    continue;
                }

                if ($adaptation->newName !== null) {
                    continue;
                }

                if (in_array($adaptation->newModifier, [Class_::MODIFIER_PRIVATE, Class_::MODIFIER_PROTECTED], true)) {
                    $hidden[] = $adaptation->method->toString();
                }
            }
        }

        return $hidden;
    }
}
