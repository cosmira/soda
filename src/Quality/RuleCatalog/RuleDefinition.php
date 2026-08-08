<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\RuleCatalog;

/**
 * Single source fields for one quality rule (default threshold, presentation).
 */
final readonly class RuleDefinition
{
    public function __construct(
        public RuleDefinitionFields $fields,
    ) {}

    public static function of(
        RuleIdentity $identity,
        RulePresentation $presentation,
        RuleScoring $scoring,
    ): self {
        return new self(new RuleDefinitionFields($identity, $presentation, $scoring));
    }

    public function withInit(RuleInitDefinition $init): self
    {
        return new self(new RuleDefinitionFields(
            $this->fields->identity,
            $this->fields->presentation,
            $this->fields->scoring,
            $init,
        ));
    }

    /**
     * @return array{severity: 'error'|'warning', label: string, comparison?: 'min'|'max'}
     */
    public function toMetadataEntry(): array
    {
        $presentation = $this->fields->presentation;
        $row = [
            'severity' => $presentation->severity,
            'label'    => $presentation->label,
        ];

        if ($presentation->comparison !== null) {
            $row['comparison'] = $presentation->comparison;
        }

        return $row;
    }
}
