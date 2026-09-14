<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Rules\Structure\TellDontAskVisitor;
use Cosmira\Soda\Tests\ParsesPhpSnippets;
use PHPUnit\Framework\TestCase;

final class TellDontAskVisitorTest extends TestCase
{
    use ParsesPhpSnippets;

    private function parseAndCollect(string $code): array
    {
        $visitor = new TellDontAskVisitor();
        $this->traversePhpFile($code, $visitor);

        return $visitor->violations();
    }

    public function testCollectsAskThenTellPattern(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Order {
    public function finalize(): void {
        if ($this->customer->isActive()) {
            $this->customer->notify();
        }
    }
}
PHP;

        $result = $this->parseAndCollect($code);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['line']);
        $this->assertSame('$this->customer', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
        $this->assertSame('App\Order::finalize', $result[0]['method']);
    }

    public function testIgnoresWhenCommandTargetsDifferentReceiver(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Order {
    public function finalize(Customer $customer, Logger $logger): void {
        if ($customer->isActive()) {
            $logger->info('active');
        }
    }
}
PHP;

        $this->assertSame([], $this->parseAndCollect($code));
    }

    // ---------------- Новые тесты ----------------

    public function testMultipleConditions(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Order {
    public function finalize(): void {
        if ($this->customer->isActive() && $this->customer->hasEmail()) {
            $this->customer->sendEmail();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('sendEmail', $result[0]['command']);
    }

    public function testElseCondition(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Payment {
    public function process(): void {
        if ($this->account->isVerified()) {
            $this->account->debit(100);
        } else {
            $this->account->notifyUnverified();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(2, $result);
        $this->assertSame('isVerified', $result[0]['question']);
        $this->assertSame('debit', $result[0]['command']);
        $this->assertSame('notifyUnverified', $result[1]['command']);
    }

    public function testIgnoresWhenQuestionAndCommandTargetDifferentObjectsViaChain(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Cart {
    public function checkout(): void {
        if ($this->user->profile()->isAdult()) {
            $this->user->allowPurchase();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertSame([], $result);
    }

    public function testLocalVariableQuestion(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Invoice {
    public function send(): void {
        $active = $customer->isActive();
        if ($active) {
            $customer->sendInvoice();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('sendInvoice', $result[0]['command']);
        $this->assertSame('$customer', $result[0]['receiver']);
    }

    public function testIgnoresPlainAssignmentsWithoutCrashing(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Example {
    public function render(object $user, string $line): void {
        $line = trim($line);
        if ($user->isActive()) {
            $user->notify();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testNestedConditions(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Subscription {
    public function renew(): void {
        if ($user->isActive()) {
            if ($user->hasValidPaymentMethod()) {
                $user->charge();
            }
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(2, $result);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('charge', $result[1]['command']);
    }

    public function testMultipleCommandsAfterQuestion(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Order {
    public function finalize(): void {
        if ($customer->isActive()) {
            $customer->notify();
            $customer->logActivity();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(2, $result);
        $this->assertSame('notify', $result[0]['command']);
        $this->assertSame('logActivity', $result[1]['command']);
    }

    public function testLoopWithQuestion(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Group {
    public function notifyAll(): void {
        foreach ($members as $member) {
            if ($member->isSubscribed()) {
                $member->sendNotification();
            }
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('isSubscribed', $result[0]['question']);
        $this->assertSame('sendNotification', $result[0]['command']);
    }

    public function testControllerArgumentQuestion(): void
    {
        $code = <<<'PHP'
<?php
namespace App\Http\Controllers;
class UserController {
    public function updateProfile(User $user): void {
        if ($user->isActive()) {
            $user->sendProfileUpdateEmail();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$user', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('sendProfileUpdateEmail', $result[0]['command']);
    }

    public function testMultipleObjectsQuestion(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Order {
    public function finalize(): void {
        if ($customer->isActive()) {
            $customer->notify();
        }
        if ($supplier->isAvailable()) {
            $supplier->ship();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(2, $result);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
        $this->assertSame('isAvailable', $result[1]['question']);
        $this->assertSame('ship', $result[1]['command']);
    }

    public function testMethodWithReturnQuestion(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Report {
    public function generate(): string {
        if ($user->isAdmin()) {
            $user->notifyAdmin();
        }
        return 'done';
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('isAdmin', $result[0]['question']);
        $this->assertSame('notifyAdmin', $result[0]['command']);
    }

    public function testChainedMethodCalls(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class Order {
    public function finalize(): void {
        if ($order->getCustomer()->isActive()) {
            $order->getCustomer()->notify();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$order->getCustomer()', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testLoopWithPropertyAccess(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class OrdersProcessor {
    public function process(): void {
        foreach ($orders as $order) {
            if ($order->customer->isActive()) {
                $order->customer->notify();
            }
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$order->customer', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testStaticMethodCalls(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class CustomerHelper {
    public function notifyCustomer(): void {
        if (Customer::isActive($customerId)) {
            Customer::notify($customerId);
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('Customer', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testLogicalExpressionInIf(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class UserManager {
    public function notify(): void {
        if ($user->isActive() || $user->isAdmin()) {
            $user->notify();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$user', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testTernaryOperator(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class UserManager {
    public function notify(): void {
        $user->isActive() ? $user->notify() : null;
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$user', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testClosureFunctionCall(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class UserManager {
    public function notifyAll(array $users): void {
        array_map(fn($u) => $u->isActive() ? $u->notify() : null, $users);
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$u', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testVariableAssignmentThenCommand(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class UserManager {
    public function notify(User $user): void {
        $active = $user->isActive();
        $active && $user->notify();
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$user', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testIgnoresCommandsForDifferentReceiverAfterSingleQuestion(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class OrderProcessor {
    public function process(): void {
        if ($customer->isActive()) {
            $customer->notify();
            $supplier->ship();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$customer', $result[0]['receiver']);
        $this->assertSame('isActive', $result[0]['question']);
        $this->assertSame('notify', $result[0]['command']);
    }

    public function testInterfaceOrAbstractCall(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class PaymentProcessor {
    public function process(Payment $payment): void {
        if ($payment->getAccount()->isVerified()) {
            $payment->getAccount()->debit();
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$payment->getAccount()', $result[0]['receiver']);
        $this->assertSame('isVerified', $result[0]['question']);
        $this->assertSame('debit', $result[0]['command']);
    }

    public function testMethodCallWithArguments(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class UserManager {
    public function grant(User $user): void {
        if ($user->hasRole('admin')) {
            $user->grantAccess('dashboard');
        }
    }
}
PHP;
        $result = $this->parseAndCollect($code);
        $this->assertCount(1, $result);
        $this->assertSame('$user', $result[0]['receiver']);
        $this->assertSame('hasRole', $result[0]['question']);
        $this->assertSame('grantAccess', $result[0]['command']);
    }

    public function testAnonymousClassAskThenTellDoesNotLeakIntoOuterMethod(): void
    {
        $code = <<<'PHP'
<?php
namespace App;
class UserManager {
    public function build(): object {
        return new class {
            public function ignored(User $user): void {
                if ($user->isActive()) {
                    $user->notify();
                }
            }
        };
    }
}
PHP;

        $this->assertSame([], $this->parseAndCollect($code));
    }
}
