<?php

// SPDX-FileCopyrightText: Copyright (c) 2019-2026 Aibolit
// SPDX-License-Identifier: MIT
// Selected Java fixture scenarios adapted to PHP; see THIRD_PARTY_NOTICES.md.

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Rules\Complexity\NoEmptyFinallyBlocks;
use Cosmira\Soda\Rules\Complexity\NoRedundantRethrow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionChecksTest extends TestCase
{
    #[DataProvider('cases')]
    public function testDetectsOnlyTheDocumentedSyntax(string $source, array $expected): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-exceptions-');
        file_put_contents($path, "<?php\n".$source);

        try {
            $rules = [new NoRedundantRethrow, new NoEmptyFinallyBlocks];
            $full = CheckFixture::collect($path, $rules);
            $selective = CheckFixture::collect($path, $rules, []);
            self::assertEquals($full['violations'], $selective['violations']);
            self::assertSame($expected, array_map(fn ($v): string => $v->rule, $full['violations']));
            foreach ($full['violations'] as $violation) {
                self::assertSame(1, $violation->value);
                self::assertSame(0, $violation->threshold);
                self::assertGreaterThanOrEqual(2, $violation->line);
                self::assertSame($path, $violation->file);
            }
        } finally {
            unlink($path);
        }
    }

    public static function cases(): iterable
    {
        yield 'Aibolit Simple adapted' => ['try { work(); } catch (Exception $error) { throw $error; }', ['no_redundant_rethrow']];
        yield 'union catch' => ['try { work(); } catch (One|Two $error) { throw $error; }', ['no_redundant_rethrow']];
        yield 'Aibolit CatchWithFunctions deliberately becomes negative' => ['try { work(); } catch (Exception $error) { logError($error); throw $error; }', []];
        yield 'side effect without using exception' => ['try { work(); } catch (Exception $error) { cleanup(); throw $error; }', []];
        yield 'conditional throw' => ['try { work(); } catch (Exception $error) { if (retrying()) { throw $error; } }', []];
        yield 'exception translation' => ['try { work(); } catch (Exception $error) { throw new DomainException("failed", previous: $error); }', []];
        yield 'different thrown variable' => ['try { work(); } catch (Exception $error) { throw $other; }', []];
        yield 'catch without binding' => ['try { work(); } catch (Exception) { throw $other; }', []];
        yield 'earlier narrow catch shields later handler' => ['try { work(); } catch (RuntimeException $error) { throw $error; } catch (Exception $error) { recover($error); }', []];
        yield 'two rethrow catches only final considered' => ['try { work(); } catch (One $error) { throw $error; } catch (Two $error) { throw $error; }', ['no_redundant_rethrow']];
        yield 'nested try has its own candidate' => ['try { try { work(); } catch (One $error) { throw $error; } } catch (Two $error) { recover($error); }', ['no_redundant_rethrow']];
        yield 'closure nested in catch is behavior' => ['try { work(); } catch (One $error) { $later = function () use ($error) { throw $error; }; }', []];
        yield 'anonymous class method' => ['new class { function run() { try { work(); } catch (One $error) { throw $error; } } };', ['no_redundant_rethrow']];
        yield 'trait method' => ['trait Work { function run() { try { work(); } catch (One $error) { throw $error; } } }', ['no_redundant_rethrow']];
        yield 'catch binding observable in finally still requires review' => ['try { work(); } catch (One $error) { throw $error; } finally { observe($error ?? null); }', ['no_redundant_rethrow']];
        yield 'comments and empty statements do not execute' => ['try { work(); } catch (One $renamed) { /* explanation */ ; throw $renamed; ; }', ['no_redundant_rethrow']];
        yield 'Aibolit Empty finally adapted' => ['try { work(); } finally {}', ['no_empty_finally_blocks']];
        yield 'Aibolit NoFinally adapted' => ['try { work(); } catch (Exception $error) { recover($error); }', []];
        yield 'Aibolit NotEmpty adapted' => ['try { work(); } finally { cleanup(); }', []];
        yield 'comment only finally' => ['try { work(); } finally { /* explain */ ; }', ['no_empty_finally_blocks']];
        yield 'finally with return executes' => ['function run() { try { work(); } finally { return; } }', []];
        yield 'nested finally blocks retain inner and outer effects' => ['try { work(); } finally { try { cleanup(); } finally {} }', ['no_empty_finally_blocks']];
        yield 'empty finally after catch' => ['try { work(); } catch (Exception $error) { recover($error); } finally {}', ['no_empty_finally_blocks']];
    }

    public function testReportsKeywordLinesAndExplainsTheRequiredTryUnwrapping(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'soda-lines-');
        file_put_contents($path, "<?php\ntry {\n work();\n}\ncatch (Exception \$error) {\n throw \$error;\n}\nfinally {\n}\ntry { work(); } finally {}\n");

        try {
            $result = CheckFixture::collect($path, [new NoRedundantRethrow, new NoEmptyFinallyBlocks]);
            self::assertSame([5, 8, 10], array_column($result['violations'], 'line'));
            self::assertStringContainsString('unwrapping the try body', $result['violations'][2]->message);
        } finally {
            unlink($path);
        }
    }

    public function testNewRulesAreDiscoverableButNeverEnabledByExistingBundles(): void
    {
        $ids = array_map(fn ($rule): string => $rule->id(), RuleCatalog::standard());
        $section = array_map(fn ($rule): string => $rule->id(), RuleCatalog::checks('complexity'));
        foreach (['no_redundant_rethrow', 'no_empty_finally_blocks'] as $id) {
            self::assertArrayHasKey($id, RuleCatalog::definitions());
            self::assertNotContains($id, $ids);
            self::assertNotContains($id, $section);
        }
        self::assertCount(81, $ids);
    }
}
