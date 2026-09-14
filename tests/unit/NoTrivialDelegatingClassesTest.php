<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Analysis\Runner as QualityAnalyser;
use Cosmira\Soda\Rules\Structure\NoTrivialDelegatingClasses;
use Cosmira\Soda\Rules\Structure\TrivialDelegatingClassAnalyser;
use Cosmira\Soda\Rules\Structure\TrivialDelegatingClassFinding;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoTrivialDelegatingClassesTest extends TestCase
{
    public function testReportsPromotedDependencyPassThrough(): void
    {
        $source = <<<'PHP'
<?php
final class OrderSaver
{
    public function __construct(private OrderRepository $repository) {}

    public function save(Order $order): void
    {
        $this->repository->save($order);
    }
}
PHP;

        $findings = $this->analyse($source);

        $this->assertCount(1, $findings);
        $this->assertSame('OrderSaver', $findings[0]->class);
        $this->assertSame('save', $findings[0]->method);
        $this->assertSame('repository', $findings[0]->delegate);
        $this->assertSame(2, $findings[0]->line);
    }

    public function testReportsTraditionalConstructorWiringAndReturnedDelegation(): void
    {
        $source = <<<'PHP'
<?php
final class UserFinder
{
    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function find(UserId $id): User
    {
        return $this->repository->find($id);
    }
}
PHP;

        $findings = $this->analyse($source);

        $this->assertCount(1, $findings);
        $this->assertSame('UserFinder', $findings[0]->class);
        $this->assertSame('find', $findings[0]->method);
    }

    public function testRuleBuildsActionableClassAndMethodViolation(): void
    {
        $source = <<<'PHP'
<?php
final class OrderSaver
{
    public function __construct(private OrderRepository $repository) {}

    public function save(Order $order): void
    {
        $this->repository->save($order);
    }
}
PHP;
        $nodes = $this->parse($source);

        $violations = (new NoTrivialDelegatingClasses)->checkFile(new FileFacts('/project/src/OrderSaver.php', $source, $nodes, []));

        $this->assertCount(1, $violations);
        $this->assertSame('no_trivial_delegating_classes', $violations[0]->rule);
        $this->assertSame('OrderSaver', $violations[0]->class);
        $this->assertSame('save', $violations[0]->method);
        $this->assertSame(2, $violations[0]->line);
        $this->assertStringContainsString('only forwards unchanged arguments', (string) $violations[0]->message);
        $this->assertStringContainsString('explicit contract or behavior', (string) $violations[0]->message);
    }

    public function testProductionPipelineLoadsRuleFromConfigAndReportsQualifiedClass(): void
    {
        $directory = sys_get_temp_dir().'/soda-trivial-delegation-'.uniqid();
        mkdir($directory, 0700, true);
        $sourcePath = $directory.'/OrderSaver.php';
        $configPath = $directory.'/soda.php';

        file_put_contents($sourcePath, <<<'PHP'
<?php
namespace App\Order;

final class OrderSaver
{
    public function __construct(private OrderRepository $repository) {}
    public function save(Order $order): void { $this->repository->save($order); }
}
PHP);
        file_put_contents($configPath, <<<'PHP'
<?php
return \Cosmira\Soda\Config\Soda::configure()->with([
    new \Cosmira\Soda\Rules\Structure\NoTrivialDelegatingClasses(),
]);
PHP);

        try {
            $result = (new QualityAnalyser)->analyse([$sourcePath], $configPath);

            $this->assertCount(1, $result->violations);
            $this->assertSame('no_trivial_delegating_classes', $result->violations[0]->rule);
            $this->assertSame('App\Order\OrderSaver', $result->violations[0]->class);
        } finally {
            unlink($sourcePath);
            unlink($configPath);
            rmdir($directory);
        }
    }

    #[DataProvider('legitimateSmallClassProvider')]
    public function testAllowsSmallClassWithIndependentReasonToExist(string $source): void
    {
        $this->assertSame([], $this->analyse($source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function legitimateSmallClassProvider(): iterable
    {
        yield 'strategy with explicit interface' => [<<<'PHP'
<?php
final class OrderSaver implements SavesOrders
{
    public function __construct(private OrderRepository $repository) {}
    public function save(Order $order): void { $this->repository->save($order); }
}
PHP];

        yield 'specialized subtype' => [<<<'PHP'
<?php
final class DomainFailure extends RuntimeException
{
    public function message(string $key): string { return $this->translator->message($key); }
}
PHP];

        yield 'value object exposes state' => [<<<'PHP'
<?php
final readonly class UserId
{
    public function __construct(private string $value) {}
    public function value(): string { return $this->value; }
}
PHP];

        yield 'method adds a policy decision' => [<<<'PHP'
<?php
final class OrderSaver
{
    public function __construct(private OrderRepository $repository) {}
    public function save(Order $order): void
    {
        if ($order->isValid()) {
            $this->repository->save($order);
        }
    }
}
PHP];

        yield 'adapter translates operation name' => [<<<'PHP'
<?php
final class OrderSaver
{
    public function __construct(private OrderRepository $repository) {}
    public function save(Order $order): void { $this->repository->persist($order); }
}
PHP];

        yield 'adapter transforms an argument' => [<<<'PHP'
<?php
final class OrderSaver
{
    public function __construct(private OrderRepository $repository) {}
    public function save(Order $order): void { $this->repository->save($order->normalized()); }
}
PHP];

        yield 'class owns additional state' => [<<<'PHP'
<?php
final class RetryingOrderSaver
{
    public function __construct(
        private OrderRepository $repository,
        private int $attempts,
    ) {}
    public function save(Order $order): void { $this->repository->save($order); }
}
PHP];

        yield 'constructor establishes an invariant' => [<<<'PHP'
<?php
final class OrderSaver
{
    public function __construct(private OrderRepository $repository)
    {
        assert($repository->isWritable());
    }
    public function save(Order $order): void { $this->repository->save($order); }
}
PHP];

        yield 'attributed framework boundary' => [<<<'PHP'
<?php
#[Controller]
final class OrderSaver
{
    public function __construct(private OrderRepository $repository) {}
    public function save(Order $order): void { $this->repository->save($order); }
}
PHP];

        yield 'more than one operation' => [<<<'PHP'
<?php
final class OrderGateway
{
    public function __construct(private OrderRepository $repository) {}
    public function save(Order $order): void { $this->repository->save($order); }
    public function delete(Order $order): void { $this->repository->delete($order); }
}
PHP];

        yield 'anonymous class' => [<<<'PHP'
<?php
$saver = new class($repository) {
    public function __construct(private OrderRepository $repository) {}
    public function save(Order $order): void { $this->repository->save($order); }
};
PHP];
    }

    /**
     * @return list<TrivialDelegatingClassFinding>
     */
    private function analyse(string $source): array
    {
        return (new TrivialDelegatingClassAnalyser)->analyse($this->parse($source));
    }

    /**
     * @return list<Node>
     */
    private function parse(string $source): array
    {
        return (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
    }
}
