<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Documentation;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

/**
 * Flags PHPDoc descriptions that add nothing beyond the member's own name.
 *
 * A description built from filler verbs ("stores", "defines", "holds") and generic
 * context nouns ("this instance", "this policy") restates the declaration. It trains
 * readers to skip docblocks, so the ones carrying real insight get skipped too.
 * Either describe the member's purpose or delete the comment.
 *
 * @example Register in soda.php:
 *
 *   new NoVacuousDocblocks(extraFiller: ['metadata', 'payload']);
 */
#[Rule(
    label: 'Vacuous PHPDoc:',
    advice: "Describe the member's purpose in its PHPDoc, or delete the comment when the declaration already says enough.",
)]
final class NoVacuousDocblocks extends Check
{
    /**
     * Words that never make a description informative on their own, merged with extraFiller.
     */
    private const FILLER = [
        // Filler verbs opening generated prose.
        'stores', 'store', 'retain', 'retains', 'holds', 'hold', 'defines', 'define',
        'initialize', 'initializes', 'initialised', 'marks', 'mark', 'describes', 'describe',
        'indicates', 'indicate', 'carries', 'carry', 'captures', 'capture', 'contains', 'contain',
        // Articles, prepositions and connectives.
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'with', 'by', 'for', 'from',
        'that', 'this', 'these', 'those', 'it', 'its', 'is', 'are', 'was', 'were', 'be', 'been',
        'as', 'at', 'into', 'without', 'within', 'across', 'between', 'during', 'before', 'after',
        'under', 'over', 'via', 'per', 'when', 'while', 'their', 'not', 'no', 'so', 'such',
        'than', 'then', 'there', 'where', 'which', 'if', 'because', 'since', 'until', 'whether',
        // Generic context nouns and quantifiers common in generated boilerplate.
        'instance', 'instances', 'analysis', 'policy', 'configured', 'configuration', 'used',
        'usage', 'use', 'uses', 'using', 'shared', 'current', 'active', 'value', 'values',
        'field', 'fields', 'entry', 'entries', 'item', 'items', 'element', 'elements',
        'collaborator', 'collaborators', 'declaration', 'declarations', 'member', 'members',
    ];

    /**
     * Merge extra filler vocabulary with the built-in one.
     *
     * @param string[] $extraFiller Additional words treated as carrying no information.
     */
    public function __construct(
        private readonly array $extraFiller = [],
    ) {}

    /**
     * Return the stable identifier used in diagnostics.
     */
    #[\Override]
    public function id(): string
    {
        return 'no_vacuous_docblocks';
    }

    /**
     * Inspect declarations in the already parsed syntax tree.
     *
     * @return list<string>
     */
    #[\Override]
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Report every documented member whose description carries no content words.
     *
     * @return iterable<Violation>
     */
    #[\Override]
    public function checkFile(FileFacts $file): iterable
    {
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, ClassLike::class) as $class) {
            yield from $this->classViolations($file, $class);
        }
    }

    /**
     * Inspect the properties, constants and methods of one class-like declaration.
     *
     * @return iterable<Violation>
     */
    private function classViolations(FileFacts $file, ClassLike $class): iterable
    {
        $owner = $class->name?->toString() ?? 'anonymous@'.$class->getLine();

        foreach ($class->getProperties() as $property) {
            yield from $this->propertyViolations($file, $owner, $property);
        }

        foreach ($class->getConstants() as $constant) {
            yield from $this->constantViolations($file, $owner, $constant);
        }

        foreach ($class->getMethods() as $method) {
            yield from $this->methodViolations($file, $owner, $method);
        }
    }

    /**
     * Inspect each name declared by one property statement.
     *
     * @return iterable<Violation>
     */
    private function propertyViolations(FileFacts $file, string $owner, Property $property): iterable
    {
        foreach ($property->props as $item) {
            $name = $item->name->toString();
            yield from $this->violations($file, $owner, $property, $name, '$'.$name);
        }
    }

    /**
     * Inspect each name declared by one constant statement.
     *
     * @return iterable<Violation>
     */
    private function constantViolations(FileFacts $file, string $owner, ClassConst $constant): iterable
    {
        foreach ($constant->consts as $item) {
            $name = $item->name->toString();
            yield from $this->violations($file, $owner, $constant, $name, $name);
        }
    }

    /**
     * Inspect one method and the parameters it promotes to properties.
     *
     * @return iterable<Violation>
     */
    private function methodViolations(FileFacts $file, string $owner, ClassMethod $method): iterable
    {
        $name = $method->name->toString();
        yield from $this->violations($file, $owner, $method, $name, $name.'()');

        foreach ($method->params as $parameter) {
            if (! $parameter->isPromoted()) {
                continue;
            }

            yield from $this->promotedParameterViolations($file, $owner, $parameter);
        }
    }

    /**
     * Inspect one promoted parameter declared inside a constructor signature.
     *
     * @return iterable<Violation>
     */
    private function promotedParameterViolations(FileFacts $file, string $owner, Node\Param $parameter): iterable
    {
        $name = (string) $parameter->var->name;

        yield from $this->violations($file, $owner, $parameter, $name, '$'.$name);
    }

    /**
     * Report the documented member once its description has no content words left.
     *
     * @return iterable<Violation>
     */
    private function violations(FileFacts $file, string $owner, Node $node, string $name, string $label): iterable
    {
        $comment = $node->getDocComment();
        if (! $comment instanceof Doc) {
            return;
        }

        $hasContent = $this->contentWords($this->prose($comment), $this->nameWords($name)) !== [];
        if ($hasContent) {
            return;
        }

        yield new Violation(
            rule: $this->id(), file: $file->path, value: 1, threshold: 0,
            class: $owner, line: $node->getLine(),
            message: sprintf('PHPDoc on %s adds nothing beyond the name; describe the purpose or delete the comment.', $label),
        );
    }

    /**
     * Extract the prose before the first tag from a PHPDoc block.
     */
    private function prose(Doc $comment): string
    {
        $split = preg_split('/\R/', trim($comment->getText()));
        $lines = $split === false ? [] : $split;

        $prose = [];
        foreach ($lines as $line) {
            $content = trim(ltrim(trim((string) preg_replace('~^\s*/\*+\s*~', '', $line)), '*'));
            $content = trim((string) preg_replace('~\*/\s*$~', '', $content));

            if (str_starts_with($content, '@')) {
                break;
            }

            if ($content !== '') {
                $prose[] = $content;
            }
        }

        return implode(' ', $prose);
    }

    /**
     * Return words that survive after removing the identifier and filler vocabulary.
     *
     * @param list<string> $nameWords
     *
     * @return list<string>
     */
    private function contentWords(string $prose, array $nameWords): array
    {
        $filler = array_merge(self::FILLER, $this->extraFiller);
        $ignored = array_merge($filler, $nameWords);
        preg_match_all('/[\p{L}]+/u', $prose, $matches);

        $content = [];
        foreach ($matches[0] as $word) {
            $word = mb_strtolower($word);
            $singular = rtrim($word, 's');

            if (in_array($word, $ignored, true) || in_array($singular, $filler, true)) {
                continue;
            }

            $content[] = $word;
        }

        return $content;
    }

    /**
     * Split an identifier into lowercase words at camelCase and snake_case boundaries.
     *
     * @return list<string>
     */
    private function nameWords(string ...$names): array
    {
        $words = [];
        foreach ($names as $name) {
            $parts = preg_split('/(?<=[a-z0-9])(?=[A-Z])|_+/', $name);
            $parts = $parts === false ? [] : $parts;

            foreach ($parts as $word) {
                $word = strtolower(trim($word, '_$'));
                if ($word !== '') {
                    $words[] = $word;
                }
            }
        }

        return $words;
    }
}
