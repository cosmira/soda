<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Naming;

use Illuminate\Support\Str;

/**
 * Анализ избыточного именования: имя повторяет информацию из типов.
 *
 * Алгоритм сравнения слов:
 * - Разбивка camelCase через Str::ucsplit (Laravel)
 * - Порог схожести: similar_text ≥ 80%
 * - Минимальная длина слова: 4 (игнор коротких: add, get, has)
 */
final readonly class RedundantNamingAnalyser
{
    /**
     * Defines similarity threshold used by this policy.
     */
    private const float SIMILARITY_THRESHOLD = 80.0;

    /**
     * Defines min word length used by this policy.
     */
    private const int MIN_WORD_LENGTH = 4;

    /**
     * Defines class suffix ignore used by this policy.
     */
    private const array CLASS_SUFFIX_IGNORE = [
        'Exception', 'Interface', 'Trait', 'Abstract', 'Test',
    ];

    /**
     * Defines generic suffixes used by this policy.
     */
    private const array GENERIC_SUFFIXES = ['Collection', 'Repository', 'Service', 'Manager', 'Factory', 'Handler', 'Provider'];

    /**
     * Defines redundant middle used by this policy.
     */
    private const array REDUNDANT_MIDDLE = ['Item', 'Items', 'Entity', 'Entities', 'Model', 'Models'];

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(
        private float $similarityThreshold = self::SIMILARITY_THRESHOLD,
        private int $minWordLength = self::MIN_WORD_LENGTH,
    ) {}

    /**
     * @param array{classes: list<array{class: string, line: int}>, methods: list<array{name: string, methodName: string, class: string|null, firstParamType: string|null, returnType: string|null, line: int, isPublic: bool}>} $data
     *
     * @return list<array{type: 'class'|'method', current: string, suggested: string, reason: string, similarity: float, line: int, className?: string}>
     */
    public function analyse(array $data): array
    {
        $violations = [];
        $methodChecker = new MethodRedundancyChecker($this->similarityThreshold, $this->minWordLength);

        foreach ($data['classes'] as $classData) {
            $v = $this->checkClass($classData);
            if ($v !== null) {
                $violations[] = $v;
            }
        }

        foreach ($data['methods'] as $methodData) {
            if ($methodData['isPublic']) {
                $v = $methodChecker->check($methodData);
                if ($v !== null) {
                    $violations[] = $v;
                }
            }
        }

        return $violations;
    }

    /**
     * @return array{type: 'class', current: string, suggested: string, reason: string, similarity: float, line: int}|null
     */
    private function checkClass(array $classData): ?array
    {
        $shortName = $this->shortClassName($classData['class']);
        if ($this->shouldIgnoreClass($shortName)) {
            return null;
        }

        $words = $this->splitCamelCase($shortName);
        $wordCount = count($words);
        if ($wordCount < 3) {
            return null;
        }

        $lastWord = $words[array_key_last($words)] ?? '';
        $middle = $words[$wordCount - 2];
        $prefix = implode('', array_slice($words, 0, -2));
        $suggested = $prefix.$lastWord;

        $ok = strlen($lastWord) >= $this->minWordLength
            && in_array($lastWord, self::GENERIC_SUFFIXES, true)
            && in_array($middle, self::REDUNDANT_MIDDLE, true)
            && $prefix !== ''
            && $suggested !== $shortName;

        return $ok ? [
            'type'       => 'class',
            'current'    => $shortName,
            'suggested'  => $suggested,
            'reason'     => sprintf('"%s" redundant before "%s"', $middle, $lastWord),
            'similarity' => 100.0,
            'line'       => $classData['line'],
        ] : null;
    }

    /**
     * Remove the namespace prefix from a qualified class name.
     */
    private function shortClassName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }

    /**
     * Decide whether to ignore class under the current policy.
     */
    private function shouldIgnoreClass(string $shortName): bool
    {
        foreach (self::CLASS_SUFFIX_IGNORE as $suffix) {
            if (str_ends_with($shortName, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function splitCamelCase(string $name): array
    {
        return array_values(array_filter(Str::ucsplit($name), fn (string $p) => $p !== ''));
    }
}
