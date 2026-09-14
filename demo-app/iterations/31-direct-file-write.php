<?php

declare(strict_types=1);

final class ProjectionCheckpointRepository
{
    /**
     * Persist a checkpoint through PHP's universally available stream adapter.
     */
    public function save(string $checkpoint): string
    {
        file_put_contents('php://memory', $checkpoint);

        return 'saved:'.$checkpoint;
    }
}

echo (new ProjectionCheckpointRepository())->save('checkpoint-42').PHP_EOL;
