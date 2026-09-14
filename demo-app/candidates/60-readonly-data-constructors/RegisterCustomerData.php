<?php

declare(strict_types=1);

namespace DemoApp\Customers;

final readonly class RegisterCustomerData
{
    public function __construct(
        public string $email,
        public string $name,
        public string $locale,
        public string $source,
    ) {}

    public function identifier(): string
    {
        return strtolower($this->email).'@'.$this->locale.'#'.$this->source;
    }
}
