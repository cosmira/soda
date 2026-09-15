<?php

declare(strict_types=1);

namespace Cosmira\Soda\Tests;

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Rules\Naming\ClassNameLength;
use Cosmira\Soda\Rules\Naming\MethodNameLength;
use Cosmira\Soda\Rules\Naming\NamespaceNameLength;
use Cosmira\Soda\Rules\Naming\VariableNameLength;
use PHPUnit\Framework\TestCase;

final class NameLengthTest extends TestCase
{
    public function testShortApiNamesNeedNoPaddingUnderDefaultPolicy(): void
    {
        $file = $this->writeFixture('<?php class User { public function me() {} public function id() {} public function to() {} public function x() {} }');

        try {
            $entry = RuleCatalog::standardDefinitions()['method_name_length'];
            foreach ([new MethodNameLength, new MethodNameLength(...$entry['arguments'])] as $rule) {
                self::assertCount(0, CheckFixture::forRule($rule, $this->context($file)));
            }
            self::assertCount(4, CheckFixture::forRule(new MethodNameLength(min: 3), $this->context($file)));
        } finally {
            unlink($file);
        }
    }

    public function testVariableNameLengthReportsTooShortName(): void
    {
        $file = $this->writeFixture('<?php $x = 1; $good = 2;');

        try {
            $violations = CheckFixture::forRule(new VariableNameLength(min: 3, max: 16), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('variable_name_length', $violations->first()->rule);
            $this->assertSame(1, $violations->first()->line);
        } finally {
            unlink($file);
        }
    }

    public function testMethodNameLengthReportsTooLongName(): void
    {
        $file = $this->writeFixture('<?php class User { public function methodNameIsTooLong(): void {} }');

        try {
            $violations = CheckFixture::forRule(new MethodNameLength(min: 3, max: 12), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('method_name_length', $violations->first()->rule);
            $this->assertSame('Name "methodNameIsTooLong" has length 19.', $violations->first()->message);
        } finally {
            unlink($file);
        }
    }

    public function testClassNameLengthReportsTooLongName(): void
    {
        $file = $this->writeFixture('<?php class VeryLongClassName {}');

        try {
            $violations = CheckFixture::forRule(new ClassNameLength(min: 3, max: 8), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('class_name_length', $violations->first()->rule);
        } finally {
            unlink($file);
        }
    }

    public function testNamespaceNameLengthReportsTooShortSegment(): void
    {
        $file = $this->writeFixture('<?php namespace App\\X\\Domain; final class User {}');

        try {
            $violations = CheckFixture::forRule(new NamespaceNameLength(min: 3, max: 16), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('namespace_name_length', $violations->first()->rule);
            $this->assertSame(1, $violations->first()->line);
            $this->assertSame(['value' => 1, 'threshold' => 3], ['value' => $violations->first()->value, 'threshold' => $violations->first()->threshold]);
            $this->assertSame('Name "X" has length 1.', $violations->first()->message);
        } finally {
            unlink($file);
        }
    }

    public function testVariableNameLengthIgnoresThisAndSuperglobals(): void
    {
        $file = $this->writeFixture('<?php class User { public function run(): void { $this->id = $_SERVER["id"] ?? null; $x = 1; } }');

        try {
            $violations = CheckFixture::forRule(new VariableNameLength(min: 3, max: 16), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('variable_name_length', $violations->first()->rule);
            $this->assertSame(['value' => 1, 'threshold' => 3], ['value' => $violations->first()->value, 'threshold' => $violations->first()->threshold]);
        } finally {
            unlink($file);
        }
    }

    public function testVariableNameLengthUsesConfiguredMaximum(): void
    {
        $file = $this->writeFixture('<?php $shortEnough = 1; $variableNameIsTooLong = 2;');

        try {
            $violations = CheckFixture::forRule(new VariableNameLength(min: 3, max: 12), $this->context($file));

            $this->assertCount(1, $violations);
            $this->assertSame('variable_name_length', $violations->first()->rule);
            $this->assertSame(['value' => 21, 'threshold' => 12], ['value' => $violations->first()->value, 'threshold' => $violations->first()->threshold]);
        } finally {
            unlink($file);
        }
    }

    private function writeFixture(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'soda-name-length-');
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
