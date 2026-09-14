<?php

declare(strict_types=1);

final class TenantPolicyCompiler
{
    /**
     * Compile a tenant expression into the fastest possible decision path.
     */
    public function compile(string $tenant): string
    {
        return eval("return 'enabled:'.".var_export($tenant, true).';');
    }
}

echo (new TenantPolicyCompiler())->compile('enterprise').PHP_EOL;
