<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\Runner;
use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Rules\Architecture\NoBooleanParameters;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Structure\NoTrivialDelegatingClasses;
use Cosmira\Soda\Rules\Usage\NoUnusedMethods;
use Cosmira\Soda\Tests\Design\Invitation;
use Cosmira\Soda\Tests\Design\MemoryStorage;
use Cosmira\Soda\Tests\Design\Money;
use Cosmira\Soda\Tests\Design\PublicationSettings;
use Cosmira\Soda\Tests\Design\QueryOptions;
use Cosmira\Soda\Tests\Design\ReportDraft;
use Cosmira\Soda\Tests\Design\ReportStorage;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../fixtures/design/practical.php';

final class PracticalDesignTest extends TestCase
{
    public function testSupportsPracticalObjectsUnderTheReviewedRuleContracts(): void
    {
        $result = CheckFixture::collect(__DIR__.'/../fixtures/design/practical.php', [
            new NoTrivialDelegatingClasses,
            new NoBooleanParameters,
        ]);

        self::assertSame([], $result['violations']);
    }

    public function testPracticalObjectsAndSeparateConcernUseTheReviewedRules(): void
    {
        $files = [__DIR__.'/../fixtures/design/practical.php', __DIR__.'/../fixtures/design/Expiration.php'];
        $config = Soda::configure()->with([new NoUnusedMethods, new NoComplexControlConditions, new NoTrivialDelegatingClasses, new NoBooleanParameters]);
        self::assertCount(0, (new Runner)->check($files, $config)->violations);
    }

    public function testConcernKeepsBehaviorOnItsOwner(): void
    {
        $invitation = new Invitation(new DateTimeImmutable('2026-09-01'));

        self::assertFalse($invitation->hasExpired(new DateTimeImmutable('2026-08-31')));
        self::assertTrue($invitation->hasExpired(new DateTimeImmutable('2026-09-01')));
    }

    public function testFluentDraftBuildsOneReadableOperation(): void
    {
        $draft = new ReportDraft;

        self::assertSame($draft, $draft->titled('Orders')->withRows(['first', 'second']));
        self::assertSame('Orders: first, second', $draft->render());
    }

    public function testExplicitCopyLeavesOriginalOptionsUnchanged(): void
    {
        $original = new QueryOptions;
        $copy = $original->limitedTo(25);

        self::assertNotSame($original, $copy);
        self::assertSame(10, $original->limit());
        self::assertSame(25, $copy->limit());
    }

    public function testReadonlyDataNeedsNoFactoryOrService(): void
    {
        self::assertTrue((new PublicationSettings(true))->enabled);
        self::assertFalse((new PublicationSettings(false))->enabled);
    }

    public function testValueObjectOwnsArithmeticAndItsInvariant(): void
    {
        $price = new Money(100, 'EUR');
        $total = $price->plus(new Money(50, 'EUR'));

        self::assertSame(100, $price->cents);
        self::assertSame(150, $total->cents);
        self::assertSame('EUR', $total->currency);
        $this->expectException(InvalidArgumentException::class);
        $price->plus(new Money(50, 'USD'));
    }

    public function testValueObjectRejectsInvalidConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(-1, 'EUR');
    }

    public function testSmallFunctionNeedsNoObjectWrapper(): void
    {
        self::assertSame('orders', Design\normalizedLabel(' Orders '));
    }

    public function testAdapterTranslatesTheBoundaryOperation(): void
    {
        $storage = new MemoryStorage;
        (new ReportStorage($storage))->save('report.txt', 'Orders');

        self::assertSame(['report.txt' => 'Orders'], $storage->files);
    }
}
