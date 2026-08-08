<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Config\SodaInitFileEmitter;
use Bunnivo\Soda\Quality\Config\RuleSections;
use Bunnivo\Soda\Quality\QualityConfig;
use Bunnivo\Soda\Quality\Report\RuleMetadata;
use Bunnivo\Soda\Quality\Rule\RuleChecker;
use Bunnivo\Soda\Quality\RuleCatalog\RuleCatalog;
use PHPUnit\Framework\TestCase;

final class RuleCatalogTest extends TestCase
{
    public function testDefinitionsMatchDefaultConfigKeys(): void
    {
        $catalogIds = array_keys(RuleCatalog::definitions());
        $configKeys = array_keys(QualityConfig::default()->rules);

        sort($catalogIds);
        sort($configKeys);

        $this->assertSame($configKeys, $catalogIds);
    }

    public function testMetadataMatchesRuleMetadataDefault(): void
    {
        $fromCatalog = RuleCatalog::metadataMap();
        $fromFacade = RuleMetadata::default();

        foreach ($fromCatalog as $id => $row) {
            $this->assertSame($row['severity'], $fromFacade->severity($id), $id);
            $this->assertSame($row['label'], $fromFacade->label($id), $id);

            if (isset($row['comparison'])) {
                $this->assertSame($row['comparison'], $fromFacade->comparison($id), $id);
            }
        }
    }

    public function testSectionsOrderedMatchesRuleSections(): void
    {
        $this->assertSame(RuleSections::sections(), RuleCatalog::sectionsOrdered());
    }

    public function testInitEmitterCoversEveryCatalogRule(): void
    {
        $catalogIds = array_keys(RuleCatalog::definitions());
        $emittedIds = SodaInitFileEmitter::ruleIds();

        sort($catalogIds);
        sort($emittedIds);

        $this->assertSame($catalogIds, $emittedIds);
    }

    public function testInitDefinitionsPointToExistingRuleCheckers(): void
    {
        foreach (RuleCatalog::initDefinitions() as $id => $init) {
            $this->assertTrue(class_exists($init->class), $id);
            $this->assertContains(RuleChecker::class, class_implements($init->class), $id);
        }
    }
}
