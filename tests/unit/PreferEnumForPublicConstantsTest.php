<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Config\SodaInitFileEmitter;
use Cosmira\Soda\Rules\Structure\PreferEnumForPublicConstants;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PreferEnumForPublicConstantsTest extends TestCase
{
    public function testEnabledInStandardAndGeneratedConfiguration(): void
    {
        $entry = RuleCatalog::standardDefinitions()['prefer_enum_for_public_constants'];
        self::assertSame(PreferEnumForPublicConstants::class, $entry['class']);
        self::assertContains(PreferEnumForPublicConstants::class, array_map(
            static fn ($rule): string => $rule::class,
            RuleCatalog::standard(),
        ));
        self::assertStringContainsString('new PreferEnumForPublicConstants()', SodaInitFileEmitter::emit());
        self::assertSame([], (new PreferEnumForPublicConstants())->requiredAnalyses());
    }

    #[Test]
    #[DataProvider('declarations')]
    public function recommendsEnumWhenClassDeclaresMoreThanTwoPublicConstants(
        string $source,
        int $expectedViolations,
        int $expectedCount = 3,
    ): void {
        $source = "<?php\n".$source;
        $file = new FileFacts(
            path: 'app/Example.php',
            source: $source,
            nodes: array_values((new ParserFactory())->createForNewestSupportedVersion()->parse($source) ?? []),
            metrics: [
                'file_loc'         => 2,
                'classes_count'    => 1,
                'classes'          => [],
                'methods'          => [],
                'namespaces'       => [],
                'interfaceParents' => [],
            ],
        );

        $violations = iterator_to_array((new PreferEnumForPublicConstants())->checkFile($file));

        self::assertCount($expectedViolations, $violations);

        foreach ($violations as $violation) {
            self::assertSame('prefer_enum_for_public_constants', $violation->rule);
            self::assertSame($expectedCount, $violation->value);
            self::assertSame(2, $violation->threshold);
            self::assertSame(2, $violation->line);
            self::assertNotNull($violation->message);
            self::assertStringContainsString('Use an enum instead.', $violation->message);
        }
    }

    /** @return iterable<string, array{0: string, 1: int, 2?: int}> */
    public static function declarations(): iterable
    {
        yield 'empty class' => ['class Example {}', 0];
        yield 'one constant' => ['class Example { public const A = 1; }', 0];
        yield 'exactly two' => ['class Example { public const A = 1; public const B = 2; }', 0];
        yield 'three public constants' => [
            'class Example { public const A = 1; public const B = 2; public const C = 3; }', 1,
        ];
        yield 'implicit public' => ['class Example { const A = 1, B = 2, C = 3; }', 1];
        yield 'grouped public declaration' => ['class Example { public const A = 1, B = 2, C = 3; }', 1];
        yield 'typed constants' => ['class Example { public const string A = "A", B = "B", C = "C"; }', 1];
        yield 'final constants' => ['class Example { final public const A = 1, B = 2, C = 3; }', 1];
        yield 'nonpublic constants excluded' => [
            'class Example { public const A = 1, B = 2; protected const C = 3; private const D = 4; }', 0,
        ];
        yield 'only private constants' => ['class Example { private const A = 1, B = 2, C = 3; }', 0];
        yield 'separate classes' => ['class One { const A = 1, B = 2; } class Two { const C = 3, D = 4; }', 0];
        yield 'inherited constants not redeclared' => [
            'class ParentClass { const A = 1, B = 2; } class ChildClass extends ParentClass { const C = 3; }', 0,
        ];
        yield 'interface excluded' => ['interface Example { const A = 1, B = 2, C = 3; }', 0];
        yield 'trait excluded' => ['trait Example { public const A = 1, B = 2, C = 3; }', 0];
        yield 'enum excluded' => ['enum Example { case A; case B; case C; public const X = 1, Y = 2, Z = 3; }', 0];
        yield 'anonymous class' => ['$example = new class { public const A = 1, B = 2, C = 3; };', 1];
        yield 'protocol example' => [
            'class Protocol {
                /** Legacy protocol mnemo for IMAP traffic. */
                public const MNEMO_IMAP = "IMAP";
                /** Legacy protocol mnemo for MAPI traffic. */
                public const MNEMO_MAPI = "MAPI";
                /** Legacy protocol mnemo for NRPC traffic. */
                public const MNEMO_NRPC = "NRPC";
                /** Legacy protocol mnemo for HTTP traffic. */
                public const MNEMO_HTTP = "HTTP";
                /** Legacy protocol mnemo for HTTPS traffic. */
                public const MNEMO_HTTPS = "HTTPS";
                /** Legacy protocol mnemo for OSCAR traffic. */
                public const MNEMO_OSCAR = "OSCAR";
            }', 1, 6,
        ];
    }
}
