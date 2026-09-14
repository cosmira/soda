<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\Runner as QualityAnalyser;
use Cosmira\Soda\Rules\Naming\NamingVisitor;
use Cosmira\Soda\Rules\Naming\RedundantNamingAnalyser;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class RedundantNamingTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndAnalyse(string $code): array
    {
        $analyser = new RedundantNamingAnalyser(80.0, 4);

        return $analyser->analyse($this->parseNaming($code));
    }

    private function parseNaming(string $code): array
    {
        $visitor = new NamingVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->facts();
    }

    /**
     * PostItemCollection → PostCollection
     */
    public function testClassPostItemCollectionSimplifiesToPostCollection(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class PostItemCollection {}
PHP;
        $violations = $this->parseAndAnalyse($code);

        $this->assertCount(1, $violations);
        $this->assertSame('class', $violations[0]['type']);
        $this->assertSame('PostItemCollection', $violations[0]['current']);
        $this->assertSame('PostCollection', $violations[0]['suggested']);
    }

    public function testPostCollectionExampleRemovesEveryRepeatedContextWord(): void
    {
        $violations = $this->parseAndAnalyse(<<<'PHP'
<?php
namespace App;

final class PostItemCollection
{
    public function addPost(Post $post): void {}
    public function hasPost(Post $post): bool { return false; }
    public function clearPost(): void {}
}
PHP);

        $suggestions = [];
        foreach ($violations as $violation) {
            $suggestions[$violation['current']] = $violation['suggested'];
        }

        $this->assertSame('PostCollection', $suggestions['PostItemCollection']);
        $this->assertSame('add(Post $...)', $suggestions['addPost(Post $...)']);
        $this->assertSame('has(Post $...)', $suggestions['hasPost(Post $...)']);
        $this->assertSame('clear()', $suggestions['clearPost()']);
    }

    public function testContextRuleKeepsExtraMeaningAndOverrideContracts(): void
    {
        $violations = $this->parseAndAnalyse(<<<'PHP'
<?php
namespace App;

final class PostCollection
{
    public function clearPostCache(): void {}

    #[\Override]
    public function clearPost(): void {}
}
PHP);

        $methodViolations = array_filter($violations, fn (array $v): bool => $v['type'] === 'method');

        $this->assertSame([], $methodViolations);
    }

    public function testProductionPipelineReportsTheCompletePostCollectionExample(): void
    {
        $directory = sys_get_temp_dir().'/soda-redundant-context-'.uniqid();
        mkdir($directory, 0700, true);
        $sourcePath = $directory.'/PostItemCollection.php';
        $configPath = $directory.'/soda.php';

        file_put_contents($sourcePath, <<<'PHP'
<?php
namespace App;

final class PostItemCollection
{
    public function addPost(Post $post): void {}
    public function hasPost(Post $post): bool { return false; }
    public function clearPost(): void {}
}
PHP);
        file_put_contents($configPath, <<<'PHP'
<?php
return \Cosmira\Soda\Config\Soda::configure()->with([
    new \Cosmira\Soda\Rules\Naming\AvoidRedundantNaming(80),
]);
PHP);

        try {
            $result = (new QualityAnalyser)->analyse([$sourcePath], $configPath);
            $messages = $result->violations
                ->map(static fn ($violation): ?string => $violation->message)
                ->all();

            $this->assertCount(4, $result->violations);
            $this->assertContains('Redundant naming: PostItemCollection → PostCollection (100%)', $messages);
            $this->assertContains('Redundant naming: addPost → add (100%)', $messages);
            $this->assertContains('Redundant naming: hasPost → has (100%)', $messages);
            $this->assertContains('Redundant naming: clearPost → clear (100%)', $messages);
        } finally {
            unlink($sourcePath);
            unlink($configPath);
            rmdir($directory);
        }
    }

    /**
     * addUserProfileData(UserProfileData $d) → add(UserProfileData $d)
     */
    public function testMethodAddUserProfileDataSimplifiesToAdd(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class UserProfileData {}
class Service {
    public function addUserProfileData(UserProfileData $d): void {}
}
PHP;
        $violations = $this->parseAndAnalyse($code);

        $methodViolations = array_filter($violations, fn (array $v) => $v['type'] === 'method');
        $this->assertNotEmpty($methodViolations);
        $v = reset($methodViolations);
        $this->assertStringContainsString('addUserProfileData', (string) $v['current']);
        $this->assertStringContainsString('add(', (string) $v['suggested']);
    }

    /**
     * getAllOrders(): Order[] → all() или getAll()
     */
    public function testMethodGetAllOrdersSimplifiesToAll(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Order {}
class OrderRepository {
    /** @return Order[] */
    public function getAllOrders(): array { return []; }
}
PHP;
        $violations = $this->parseAndAnalyse($code);

        $methodViolations = array_filter($violations, fn (array $v) => $v['type'] === 'method');
        $this->assertNotEmpty($methodViolations);
        $v = reset($methodViolations);
        $this->assertStringContainsString('getAllOrders', (string) $v['current']);
        $this->assertStringStartsWith('all', $v['suggested']);
    }

    /**
     * addUser() в UserService → add() (контекст класса)
     */
    public function testAddUserInUserServiceSimplifiesToAdd(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class UserService {
    public function addUser() {}
    public function hasUser() {}
}
PHP;
        $violations = $this->parseAndAnalyse($code);

        $methodViolations = array_filter($violations, fn (array $v) => $v['type'] === 'method');
        $this->assertCount(2, $methodViolations);
        $names = array_column($methodViolations, 'current');
        $this->assertContains('addUser()', $names);
        $this->assertContains('hasUser()', $names);
    }

    /**
     * addLogger(LoggerInterface $logger) — не трогать (false-positive)
     */
    public function testAddLoggerInterfaceNotTouched(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
interface LoggerInterface {}
class Container {
    public function addLogger(LoggerInterface $logger): void {}
}
PHP;
        $violations = $this->parseAndAnalyse($code);

        $methodViolations = array_filter($violations, fn (array $v) => $v['type'] === 'method');
        $this->assertEmpty($methodViolations);
    }

    public function testNamingVisitorCollectsInheritanceGraphAndOverrideAttribute(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
interface StatusContract {
    public function runningUnitTests(): bool;
}
abstract class BaseStatus {
    public function runningUnitTests(): bool { return true; }
}
final class AppStatus extends BaseStatus implements StatusContract {
    #[\Override]
    public function runningUnitTests(): bool { return false; }
}
PHP;

        $naming = $this->parseNaming($code);

        $this->assertArrayHasKey('types', $naming);
        $types = [];
        foreach ($naming['types'] as $type) {
            $types[$type['name']] = $type;
        }

        $this->assertSame(['runningUnitTests'], $types['App\StatusContract']['methods']);
        $this->assertSame(['App\BaseStatus', 'App\StatusContract'], $types['App\AppStatus']['inherits']);

        $methods = [];
        foreach ($naming['methods'] as $method) {
            $methods[$method['name']] = $method;
        }

        $this->assertTrue($methods['App\AppStatus::runningUnitTests']['hasOverrideAttribute']);
    }

    public function testNamingVisitorCollectsUnionReturnTypeLabel(): void
    {
        $naming = $this->parseNaming(<<<'PHP'
<?php
namespace App;
final class Application {
    public function running(): bool|null { return null; }
}
PHP);

        $methods = [];
        foreach ($naming['methods'] as $method) {
            $methods[$method['name']] = $method;
        }

        $this->assertSame('bool|null', $methods['App\Application::running']['returnType']);
    }

    public function testNamingVisitorCollectsNullableReturnTypeLabel(): void
    {
        $naming = $this->parseNaming(<<<'PHP'
<?php
namespace App;
final class Application {
    public function running(): ?bool { return null; }
}
PHP);

        $methods = [];
        foreach ($naming['methods'] as $method) {
            $methods[$method['name']] = $method;
        }

        $this->assertSame('bool|null', $methods['App\Application::running']['returnType']);
    }
}
