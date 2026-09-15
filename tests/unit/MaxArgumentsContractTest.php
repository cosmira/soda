<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Rules\Structure\MaxArguments;
use PHPUnit\Framework\TestCase;

final class MaxArgumentsContractTest extends TestCase
{
    public function testOnlyExactConfiguredExternalSignaturesAreExempt(): void
    {
        $source = <<<'PHP'
<?php
namespace App;
trait Relations {
    protected function newBelongsToMany($query, $parent, $table, $foreign, $related, $parentKey, $relatedKey, $name = null) {}
    protected function sync($query, $parent, $table, $foreign, $related, $parentKey, $relatedKey, $mode, $batch) {}
}
class OwnModel extends FrameworkModel {
    #[\Override]
    protected function newBelongsToMany($query, $parent, $table, $foreign, $related, $parentKey, $relatedKey, $name = null) {}
}
PHP;
        self::assertCount(3, $this->findings($source));
        self::assertCount(3, $this->findings($source, ['newBelongsToMany', 'FrameworkModel::newBelongsToMany']));
        $findings = $this->findings($source, ['\APP\RELATIONS::NEWBELONGSTOMANY']);
        self::assertSame(['App\Relations::sync', 'App\OwnModel::newBelongsToMany'], array_column($findings, 'method'));
        self::assertSame([9, 8], array_column($findings, 'value'));
        self::assertSame([3, 3], array_column($findings, 'threshold'));
    }

    public function testAnExplicitInheritedConstructorContractDoesNotExemptOtherConstructors(): void
    {
        $source = '<?php namespace App;
            class Relation extends FrameworkRelation { function __construct($a, $b, $c, $d) {} }
            class Worker { function __construct($a, $b, $c, $d) {} }
        ';
        $findings = $this->findings($source, ['App\Relation::__construct']);
        self::assertCount(1, $findings);
        self::assertSame('App\Worker::__construct', $findings[0]->method);
    }

    public function testNativeStreamRegistrationPreservesOnlyProtocolArity(): void
    {
        $registration = 'public static function register() { stream_wrapper_register("test", __CLASS__); }';
        self::assertSame([], $this->findings('<?php class Stream { public function stream_open($path, $mode, $options, &$opened) {} '.$registration.' }'));
        self::assertCount(1, $this->findings('<?php class Stream { public function stream_open($path, $mode, $options, &$opened) {} }'));
        self::assertCount(1, $this->findings('<?php class Stream { public function stream_open($path, $mode, $options, &$opened, $extra) {} '.$registration.' }'));
    }

    private function findings(string $source, array $contracts = []): array
    {
        $rule = new MaxArguments(3, contracts: $contracts);
        $path = tempnam(sys_get_temp_dir(), 'soda-contract-');
        file_put_contents($path, $source);

        try {
            $facts = (new FactCollector)->collect($path, $rule->requiredAnalyses());

            return iterator_to_array($rule->checkFile($facts));
        } finally {
            unlink($path);
        }
    }
}
