<?php

declare(strict_types=1);

#[AllowDynamicProperties]
final class FlexiblePartnerRecord
{
    /**
     * Hydrate partner fields without forcing every integration into one schema.
     */
    public function hydrate(string $reference): string
    {
        $this->reference = $reference;

        return $this->reference;
    }
}

echo (new FlexiblePartnerRecord())->hydrate('partner-42').PHP_EOL;
