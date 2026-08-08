<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins\Rules\ListOnlyArray;

/**
 * Single violation site inside a file (metrics + domain).
 *
 * @internal
 */
final readonly class ListOnlyArrayFinding
{
    public function __construct(
        public int $line,
        public ListOnlyArrayIssue $issue,
    ) {}

    /**
     * @return array{line: int, issue: string}
     */
    public function toMetricRow(): array
    {
        return [
            'line'  => $this->line,
            'issue' => $this->issue->value,
        ];
    }

    public static function tryFromMetricRow(mixed $row): ?self
    {
        if (! is_array($row) || ! isset($row['line']) || ! is_int($row['line'])) {
            return null;
        }

        $raw = $row['issue'] ?? null;
        $issue = is_string($raw) ? ListOnlyArrayIssue::tryFrom($raw) : null;

        return new self($row['line'], $issue ?? ListOnlyArrayIssue::StringKey);
    }

    /**
     * @return list<self>
     */
    public static function fromMetricRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            $finding = self::tryFromMetricRow($row);
            if ($finding instanceof self) {
                $out[] = $finding;
            }
        }

        return $out;
    }
}
