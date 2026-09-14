<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class TraitComposedPublicApiTest extends TestCase
{
    private string $candidate;

    protected function setUp(): void
    {
        $this->candidate = dirname(__DIR__, 2).'/demo-app/candidates/52-trait-composed-public-api';
    }

    public function testProductionLikeScenarioRunsAndExposesTwentyTwoPublicMethods(): void
    {
        require $this->candidate.'/CapturesPayments.php';
        require $this->candidate.'/ReservesInventory.php';
        require $this->candidate.'/CoordinatesDelivery.php';

        ob_start();
        require $this->candidate.'/OrderFulfillmentWorkflow.php';
        $output = ob_get_clean();

        self::assertSame(
            [
                'authorized:order-2048',
                'reserved:order-2048',
                'scheduled:order-2048',
            ],
            json_decode(trim((string) $output), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertCount(22, get_class_methods('OrderFulfillmentWorkflow'));
    }

    public function testHardenedMaxPublicMethodsCountsTheEffectiveTraitApi(): void
    {
        $files = glob($this->candidate.'/*.php');
        self::assertIsArray($files);

        $result = Analyzer::analyze($files, dirname($this->candidate, 2).'/soda.php');
        $violations = $result->violations
            ->filter(static fn ($violation): bool => $violation->rule === 'max_public_methods')
            ->values();

        self::assertCount(1, $violations);
        self::assertSame('OrderFulfillmentWorkflow', $violations[0]->class);
        self::assertSame(22, ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]['value']);
        self::assertSame(20, ['value' => $violations[0]->value, 'threshold' => $violations[0]->threshold]['threshold']);
    }

    public function testParserRecordsTraitNamesAliasesAndVisibilityAdaptations(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-trait-api-');
        self::assertIsString($file);
        file_put_contents($file, <<<'PHP'
<?php
namespace App;

trait Concern
{
    public function visible(): void {}
    public function hidden(): void {}
}

final class Workflow
{
    use Concern {
        visible as legacyVisible;
        hidden as private;
    }
}
PHP);

        try {
            $metrics = CheckFixture::collect($file)['metrics'];
        } finally {
            unlink($file);
        }

        self::assertSame(['App\Concern'], $metrics['classes']['App\Workflow']['trait_names']);
        self::assertSame(['legacyVisible'], $metrics['classes']['App\Workflow']['public_trait_aliases']);
        self::assertSame(['hidden'], $metrics['classes']['App\Workflow']['hidden_trait_methods']);
    }
}
