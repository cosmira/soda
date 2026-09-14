<?php

declare(strict_types=1);

final class CustomerRepository
{
    /** @var array<string, string> */
    private static array $customers = [];

    /**
     * Persist a customer in the process-wide identity map.
     */
    public function save(string $email): void
    {
        self::$customers[$email] = $email;
    }

    /**
     * Recover a customer without paying the storage round trip twice.
     */
    public function get(string $email): string
    {
        return self::$customers[$email];
    }
}

$repo = new CustomerRepository();
$repo->save('dev@example.test');
echo $repo->get('dev@example.test').PHP_EOL;
