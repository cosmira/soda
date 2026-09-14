<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class EffectiveTraitPublicMethodsTest extends TestCase
{
    public function testCountsNestedTraitMethodsInTheEffectiveClassApi(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([
            '/Base.php'      => $this->metrics('App\BaseConcern', $this->names('base', 10)),
            '/Composite.php' => $this->metrics(
                'App\CompositeConcern',
                ['composeOne', 'composeTwo'],
                ['App\BaseConcern'],
            ),
            '/Workflow.php' => $this->metrics(
                'App\Workflow',
                $this->names('workflow', 10),
                ['App\CompositeConcern'],
            ),
        ]));

        self::assertCount(1, $violations);
        self::assertSame(22, ['value' => $violations->first()->value, 'threshold' => $violations->first()->threshold]['value']);
    }

    public function testCountsPublicTraitAliasesAsAdditionalApi(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([
            '/Concern.php'  => $this->metrics('App\Concern', $this->names('operation', 20)),
            '/Workflow.php' => $this->metrics(
                'App\Workflow',
                [],
                ['App\Concern'],
                ['legacyOperation'],
            ),
        ]));

        self::assertCount(1, $violations);
        self::assertSame(21, ['value' => $violations->first()->value, 'threshold' => $violations->first()->threshold]['value']);
    }

    public function testDeduplicatesOverriddenAndConflictingMethodNames(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([
            '/First.php'    => $this->metrics('App\FirstConcern', ['save', 'cancel']),
            '/Second.php'   => $this->metrics('App\SecondConcern', ['save', 'retry']),
            '/Workflow.php' => $this->metrics(
                'App\Workflow',
                ['save', 'release'],
                ['App\FirstConcern', 'App\SecondConcern'],
            ),
        ], 4));

        self::assertSame([], $violations->all());
    }

    public function testAllowsSmallIdiomaticTraitComposition(): void
    {
        $violations = CheckFixture::forRule(null, $this->context([
            '/Scopes.php'  => $this->metrics('App\ScopesOrders', ['paid', 'recent']),
            '/Factory.php' => $this->metrics('App\HasFactory', ['factory']),
            '/Order.php'   => $this->metrics(
                'App\Order',
                ['place', 'cancel', 'total'],
                ['App\ScopesOrders', 'App\HasFactory'],
            ),
        ]));

        self::assertSame([], $violations->all());
    }

    public function testVisibilityAdaptationRemovesMethodUnlessClassOverridesItPublicly(): void
    {
        $traitMethods = $this->names('operation', 20);
        $traitMethods[] = 'internalHelper';

        $hidden = CheckFixture::forRule(null, $this->context([
            '/Concern.php'  => $this->metrics('App\Concern', $traitMethods),
            '/Workflow.php' => $this->metrics(
                'App\Workflow',
                [],
                ['App\Concern'],
                [],
                ['internalHelper'],
            ),
        ], 21));
        self::assertSame([], $hidden->all());

        $overridden = CheckFixture::forRule(null, $this->context([
            '/Concern.php'  => $this->metrics('App\Concern', $traitMethods),
            '/Workflow.php' => $this->metrics(
                'App\Workflow',
                ['internalHelper', 'release'],
                ['App\Concern'],
                [],
                ['internalHelper'],
            ),
        ], 21));
        self::assertCount(1, $overridden);
        self::assertSame(22, ['value' => $overridden->first()->value, 'threshold' => $overridden->first()->threshold]['value']);
    }

    /** @param array<string, array<string, mixed>> $metrics */
    private function context(array $metrics, int $limit = 20): array
    {
        $core = $metrics;

        return [CheckFixture::checks(['max_public_methods' => $limit], []), $core];
    }

    /**
     * @param list<string> $methods
     * @param list<string> $traits
     * @param list<string> $aliases
     * @param list<string> $nonPublicMethods
     *
     * @return array<string, mixed>
     */
    private function metrics(
        string $type,
        array $methods,
        array $traits = [],
        array $aliases = [],
        array $nonPublicMethods = [],
    ): array {
        return [
            'file_loc'              => 10,
            'classes_count'         => 1,
            'classes'               => [$type => [
                ...$this->classRow(count($methods), count($traits)),
                'public_method_names'  => $methods,
                'trait_names'          => $traits,
                'public_trait_aliases' => $aliases,
                'hidden_trait_methods' => $nonPublicMethods,
            ]],
            'methods'               => [],
            'namespaces'            => [],
        ];
    }

    /** @return array<string, int|string> */
    private function classRow(int $publicMethods, int $traits): array
    {
        return [
            'loc'               => 10,
            'methods'           => $publicMethods,
            'properties'        => 0,
            'public_methods'    => $publicMethods,
            'dependencies'      => 0,
            'efferent_coupling' => 0,
            'traits'            => $traits,
            'interfaces'        => 0,
            'namespace'         => 'App',
            'namespace_depth'   => 1,
        ];
    }

    /** @return list<string> */
    private function names(string $prefix, int $count): array
    {
        return array_map(
            static fn (int $number): string => $prefix.$number,
            range(1, $count),
        );
    }
}
