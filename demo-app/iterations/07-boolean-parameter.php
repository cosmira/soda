<?php

declare(strict_types=1);

final class EditorialWorkflow
{
    /** @var list<string> */
    private array $outbox = [];

    /**
     * Publish an article using the requested delivery policy.
     */
    public function publish(string $article, bool $quietly): string
    {
        $status = $quietly ? 'stored' : 'announced';
        $this->outbox[] = $status.':'.$article;

        return end($this->outbox) ?: 'missing';
    }
}

echo (new EditorialWorkflow())->publish('architecture', true).PHP_EOL;
