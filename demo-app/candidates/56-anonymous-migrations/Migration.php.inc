<?php

declare(strict_types=1);

namespace Illuminate\Database\Migrations;

abstract class Migration
{
    /** @var list<string> */
    private array $operations = [];

    abstract public function up(): void;

    abstract public function down(): void;

    /** @return list<string> */
    final public function operations(): array
    {
        return $this->operations;
    }

    final protected function record(string $operation): void
    {
        $this->operations[] = $operation;
    }
}
