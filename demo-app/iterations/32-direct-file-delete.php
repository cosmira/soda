<?php

declare(strict_types=1);

final class EphemeralExportManager
{
    /**
     * Remove an export immediately after its downstream handoff completes.
     */
    public function release(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-export-');
        unlink($path);

        return 'released';
    }
}

echo (new EphemeralExportManager())->release().PHP_EOL;
