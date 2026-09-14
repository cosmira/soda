<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests\Design;

use DateTimeImmutable;
use InvalidArgumentException;

require_once __DIR__.'/Expiration.php';

final readonly class Invitation
{
    use Expiration;

    public function __construct(private DateTimeImmutable $expiresAt) {}
}

final class ReportDraft
{
    private string $title = '';

    private array $rows = [];

    public function titled(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function withRows(array $rows): self
    {
        $this->rows = $rows;

        return $this;
    }

    public function render(): string
    {
        return $this->title.': '.implode(', ', $this->rows);
    }
}

final class QueryOptions
{
    private int $limit = 10;

    public function limitedTo(int $limit): self
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The limit must be positive.');
        }

        $copy = clone $this;
        $copy->limit = $limit;

        return $copy;
    }

    public function limit(): int
    {
        return $this->limit;
    }
}

final readonly class PublicationSettings
{
    public bool $enabled;

    public function __construct(bool $enabled)
    {
        $this->enabled = $enabled;
    }
}

final readonly class Money
{
    public function __construct(public int $cents, public string $currency)
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('The amount must not be negative.');
        }
    }

    public function plus(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('Amounts must use the same currency.');
        }

        return new self($this->cents + $other->cents, $this->currency);
    }
}

function normalizedLabel(string $label): string
{
    return strtolower(trim($label));
}

final class MemoryStorage
{
    public array $files = [];

    public function write(string $path, string $content): void
    {
        $this->files[$path] = $content;
    }
}

final readonly class ReportStorage
{
    public function __construct(private MemoryStorage $storage) {}

    public function save(string $path, string $content): void
    {
        $this->storage->write($path, $content);
    }
}
