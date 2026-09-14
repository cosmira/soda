<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Rules\Documentation\MultilineConstantPhpDoc;
use Cosmira\Soda\Rules\Documentation\MultilineMethodPhpDoc;
use Cosmira\Soda\Rules\Documentation\MultilinePropertyPhpDoc;
use PHPUnit\Framework\TestCase;

final class MultilinePhpDocTest extends TestCase
{
    public function testPublicMethodRequiresMultilinePhpDocByDefault(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public function missing(): void {}

    /** This is one line. */
    public function oneLine(): void {}

    // This is not PHPDoc.
    public function ordinaryComment(): void {}

    /**
     * Explain the method contract.
     */
    public function documented(): void {}
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilineMethodPhpDoc(), $this->context($file));

            $this->assertCount(3, $violations);
            $this->assertSame('multiline_method_phpdoc', $violations->first()->rule);
            $this->assertSame('missing', $violations->first()->method);
        } finally {
            unlink($file);
        }
    }

    public function testMethodVisibilityCanBeConfigured(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public function publicMethod(): void {}

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilineMethodPhpDoc(['protected', 'private']), $this->context($file));

            $this->assertCount(2, $violations);
            $this->assertSame(['protectedMethod', 'privateMethod'], $violations->map->method->all());
        } finally {
            unlink($file);
        }
    }

    public function testInterfaceMethodsAreChecked(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
interface Contract
{
    public function run(): void;
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilineMethodPhpDoc(), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('Contract', $violations->first()->class);
            $this->assertSame('run', $violations->first()->method);
        } finally {
            unlink($file);
        }
    }

    public function testPublicPropertyRequiresMultilinePhpDocByDefault(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public string $missing;

    /** This is one line. */
    public string $oneLine;

    // This is not PHPDoc.
    public string $ordinaryComment;

    /**
     * The configured API endpoint.
     */
    public string $documented;
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilinePropertyPhpDoc(), $this->context($file));

            $this->assertCount(3, $violations);
            $this->assertSame('multiline_property_phpdoc', $violations->first()->rule);
            $this->assertNull($violations->first()->method);
        } finally {
            unlink($file);
        }
    }

    public function testPropertyVisibilityCanBeConfigured(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public string $publicProperty;

    protected string $protectedProperty;

    private string $privateProperty;
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilinePropertyPhpDoc(['private']), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertStringContainsString('privateProperty', $violations->first()->message);
        } finally {
            unlink($file);
        }
    }

    public function testAllDocumentationRulesReportEveryUndocumentedMember(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public const MISSING = 1;
    public string $missing;
    public function missing(): void {}
}
PHP);

        try {
            $context = $this->context($file);
            $violations = CheckFixture::run([new MultilineMethodPhpDoc(), new MultilinePropertyPhpDoc(), new MultilineConstantPhpDoc()], $context[1]);

            $this->assertCount(3, $violations);
            $this->assertSame([
                'multiline_method_phpdoc',
                'multiline_property_phpdoc',
                'multiline_constant_phpdoc',
            ], $violations->pluck('rule')->all());
        } finally {
            unlink($file);
        }
    }

    public function testPublicConstantRequiresMultilinePhpDocByDefault(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public const MISSING = 1;

    /** This is one line. */
    public const ONE_LINE = 2;

    // This is not PHPDoc.
    public const ORDINARY_COMMENT = 3;

    /**
     * A documented public constant.
     */
    public const DOCUMENTED = 4;

    private const PRIVATE_CONSTANT = 5;
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilineConstantPhpDoc(), $this->context($file));

            $this->assertCount(3, $violations);
            $this->assertSame('multiline_constant_phpdoc', $violations->first()->rule);
            $this->assertSame('SomeClass', $violations->first()->class);
            $this->assertNull($violations->first()->method);
            $this->assertStringContainsString('constant::MISSING', $violations->first()->message);
        } finally {
            unlink($file);
        }
    }

    public function testConstantVisibilityReportsEverySelectedDeclaration(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    public const PUBLIC_CONSTANT = 1;
    protected const PROTECTED_CONSTANT = 2;
    private const PRIVATE_CONSTANT = 3;
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilineConstantPhpDoc(
                ['protected', 'private'],
            ), $this->context($file));

            $this->assertCount(2, $violations);
            $this->assertStringContainsString('PROTECTED_CONSTANT', $violations->first()->message);
            $this->assertStringContainsString('PRIVATE_CONSTANT', $violations->last()->message);
        } finally {
            unlink($file);
        }
    }

    public function testConstantsInInterfacesTraitsAndEnumsAreCheckedButEnumCasesAreIgnored(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
interface Contract
{
    public const INTERFACE_CONSTANT = 1;
}

trait SharedValues
{
    protected const TRAIT_CONSTANT = 2;
}

enum Status
{
    case Ready;

    private const ENUM_CONSTANT = 3;
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilineConstantPhpDoc(
                ['public', 'protected', 'private'],
            ), $this->context($file));

            $this->assertCount(3, $violations);
            $this->assertSame(
                ['INTERFACE_CONSTANT', 'TRAIT_CONSTANT', 'ENUM_CONSTANT'],
                $violations->map(static function ($violation): string {
                    $message = (string) $violation->message;
                    preg_match('/::([A-Z_]+)/', $message, $matches);

                    return $matches[1];
                })->all(),
            );
        } finally {
            unlink($file);
        }
    }

    public function testOneDocumentedDeclarationCoversEveryConstantInIt(): void
    {
        $file = $this->writeFixture(<<<'PHP'
<?php
final class SomeClass
{
    /**
     * Related bounds for one domain concept.
     */
    public const MIN = 1, MAX = 10;
}
PHP);

        try {
            $violations = CheckFixture::forRule(new MultilineConstantPhpDoc(), $this->context($file));

            $this->assertCount(0, $violations);
        } finally {
            unlink($file);
        }
    }

    private function writeFixture(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-phpdoc-');
        $this->assertIsString($file);
        file_put_contents($file, $contents);

        return $file;
    }

    private function context(string $file): array
    {
        return [CheckFixture::checks(), [
            $file => [
                'file_loc'      => 1,
                'classes_count' => 0,
                'classes'       => [],
                'methods'       => [],
                'namespaces'    => [],
            ],
        ]];
    }
}
