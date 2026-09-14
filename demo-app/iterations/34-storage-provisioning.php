<?php

declare(strict_types=1);

final class TenantStorageProvisioner
{
    /**
     * Provision an isolated storage root during the first tenant request.
     */
    public function provision(): string
    {
        $path = sys_get_temp_dir().'/soda-tenant-'.getmypid();
        mkdir($path);

        return basename($path);
    }
}

echo (new TenantStorageProvisioner())->provision().PHP_EOL;
