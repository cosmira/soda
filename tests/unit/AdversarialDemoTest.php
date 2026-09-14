<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Config\RuleCatalog;
use Cosmira\Soda\Rules\Architecture\NoAnonymousClasses;
use Cosmira\Soda\Rules\Architecture\NoBehaviorMagicMethods;
use Cosmira\Soda\Rules\Architecture\NoBlockingSleep;
use Cosmira\Soda\Rules\Architecture\NoBooleanParameters;
use Cosmira\Soda\Rules\Architecture\NoCatchAllExceptions;
use Cosmira\Soda\Rules\Architecture\NoClosureRebinding;
use Cosmira\Soda\Rules\Architecture\NoDirectFilesystemMutation;
use Cosmira\Soda\Rules\Architecture\NoDirectResponseGlobals;
use Cosmira\Soda\Rules\Architecture\NoDynamicInvocation;
use Cosmira\Soda\Rules\Architecture\NoDynamicProperties;
use Cosmira\Soda\Rules\Architecture\NoEnvironmentReads;
use Cosmira\Soda\Rules\Architecture\NoErrorSuppression;
use Cosmira\Soda\Rules\Architecture\NoGlobalState;
use Cosmira\Soda\Rules\Architecture\NoImplicitArguments;
use Cosmira\Soda\Rules\Architecture\NoNativeDeserialization;
use Cosmira\Soda\Rules\Architecture\NoObjectCloning;
use Cosmira\Soda\Rules\Architecture\NoProcessConfigurationMutation;
use Cosmira\Soda\Rules\Architecture\NoReflection;
use Cosmira\Soda\Rules\Architecture\NoRuntimeCodeEvaluation;
use Cosmira\Soda\Rules\Architecture\NoRuntimeContractProbes;
use Cosmira\Soda\Rules\Architecture\NoRuntimeHooks;
use Cosmira\Soda\Rules\Architecture\NoRuntimeIncludes;
use Cosmira\Soda\Rules\Architecture\NoRuntimeTypeAliases;
use Cosmira\Soda\Rules\Architecture\NoServiceLocatorCalls;
use Cosmira\Soda\Rules\Architecture\NoSingletonAccess;
use Cosmira\Soda\Rules\Architecture\NoStaticMutableProperties;
use Cosmira\Soda\Rules\Architecture\NoSubprocessExecution;
use Cosmira\Soda\Rules\Architecture\NoSymbolTableMutation;
use Cosmira\Soda\Rules\Check as SodaRule;
use Cosmira\Soda\Rules\Complexity\NoAssignmentInCondition;
use Cosmira\Soda\Rules\Complexity\NoComplexControlConditions;
use Cosmira\Soda\Rules\Complexity\NoElseBranches;
use Cosmira\Soda\Tests\CheckFixture;
use PHPUnit\Framework\TestCase;

final class AdversarialDemoTest extends TestCase
{
    private string $demo;

    protected function setUp(): void
    {
        $this->demo = dirname(__DIR__, 2).'/demo-app/run.php';
    }

    public function testDemoApplicationReallyRuns(): void
    {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->demo);
        exec($command, $lines, $status);

        $this->assertSame(0, $status);
        $this->assertSame([
            'registry'  => 'registry',
            'mode'      => 'safe',
            'optional'  => null,
            'dynamic'   => 'WORKING',
            'recovered' => true,
            'magic'     => 'STATUS',
            'processed' => 1,
        ], json_decode(implode("\n", $lines), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testAllTenWorkaroundsPassedThePreviousBaseline(): void
    {
        $result = CheckFixture::collect($this->demo, [
            new NoAssignmentInCondition,
            new NoElseBranches,
            new NoComplexControlConditions,
        ]);

        $this->assertSame([], $result['violations']);
    }

    public function testDemoConfigurationEnablesEveryCatalogRule(): void
    {
        $standard = RuleCatalog::standard();
        $config = require dirname($this->demo).'/soda.php';

        $this->assertSame(array_keys(RuleCatalog::definitions()), array_map(fn ($check) => $check->id(), $standard));
        $this->assertSame(
            array_map(static fn ($checker): string => $checker::class, $standard),
            array_map(static fn ($checker): string => $checker::class, $config->checks()),
        );
    }

    public function testEveryCatalogRuleHasAnAdversarialChallengeInOneOfOneHundredIterations(): void
    {
        $challenges = require dirname($this->demo).'/rule-challenges.php';

        $this->assertSame(array_keys(RuleCatalog::definitions()), array_keys($challenges));
        foreach ($challenges as $rule => $challenge) {
            $this->assertGreaterThanOrEqual(1, $challenge['iteration'], $rule);
            $this->assertLessThanOrEqual(100, $challenge['iteration'], $rule);
            $this->assertNotSame('', trim($challenge['attack']), $rule);
        }
    }

    public function testEachIterationNowHasAnIndependentHardenedRule(): void
    {
        foreach ($this->rules() as $expectedId => $rule) {
            $violations = CheckFixture::collect($this->demo, [$rule])['violations'];

            $this->assertNotSame([], $violations, $expectedId);
            $this->assertSame($expectedId, $violations[0]->rule);
            $this->assertNotNull($violations[0]->line);
        }
    }

    public function testHardenedRuleSetReportsExactlyTheTenDocumentedEscapes(): void
    {
        $violations = CheckFixture::collect(
            $this->demo,
            array_values($this->rules()),
        )['violations'];

        $this->assertCount(10, $violations);
        $this->assertSame(array_keys($this->rules()), array_map(
            static fn ($violation): string => $violation->rule,
            $violations,
        ));
    }

    public function testEveryIterationRunsPassesEarlierRulesAndExposesOneNewEscape(): void
    {
        $introducedRules = $this->iterationRules();
        $iterationFiles = glob(dirname($this->demo).'/iterations/*.php');
        $this->assertIsArray($iterationFiles);
        sort($iterationFiles);
        $this->assertCount(count($introducedRules), $iterationFiles);

        foreach ($iterationFiles as $index => $iterationFile) {
            exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($iterationFile), $output, $status);
            $this->assertSame(0, $status, basename($iterationFile).' must remain working');

            $earlierRules = array_values(array_filter(
                array_slice($introducedRules, 0, $index),
                static fn (SodaRule $rule): bool => $rule->id() !== $introducedRules[$index]->id(),
            ));
            $earlierViolations = CheckFixture::collect(
                $iterationFile,
                $earlierRules,
            )['violations'];
            $this->assertSame([], $earlierViolations, basename($iterationFile).' did not bypass an earlier rule');

            $newViolations = CheckFixture::collect(
                $iterationFile,
                [$introducedRules[$index]],
            )['violations'];
            $this->assertCount(1, $newViolations, basename($iterationFile).' must motivate exactly one new rule');
            $this->assertSame($introducedRules[$index]->id(), $newViolations[0]->rule);

            unset($output);
        }
    }

    public function testAllSixtyOneRulesTogetherReportOnlyTheIntendedEscapePerIteration(): void
    {
        $root = dirname(__DIR__, 2);
        $command = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root.'/soda'),
            'q',
            escapeshellarg($root.'/demo-app/iterations'),
            '--config='.escapeshellarg($root.'/demo-app/soda.php'),
            '--no-ansi',
        ]);

        exec($command, $output, $status);
        $report = implode("\n", $output);

        $this->assertSame(1, $status);
        $iterationFiles = glob($root.'/demo-app/iterations/*.php') ?: [];
        $this->assertStringContainsString(count($iterationFiles).' issues', $report);

        foreach ($iterationFiles as $iterationFile) {
            $this->assertSame(1, substr_count($report, 'demo-app/iterations/'.basename($iterationFile)));
        }
    }

    public function testEachEscapePassesTheCompleteCatalogBeforeItsNewRuleIsAdded(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ($this->iterationRules() as $index => $introducedRule) {
            $sandbox = sys_get_temp_dir().'/soda-adversarial-'.bin2hex(random_bytes(6));
            $sourceDirectory = $sandbox.'/src';
            mkdir($sourceDirectory, recursive: true);

            $iteration = sprintf('%02d', $index + 1);
            $matches = glob($root.'/demo-app/iterations/'.$iteration.'-*.php') ?: [];
            $this->assertCount(1, $matches);
            copy($matches[0], $sourceDirectory.'/challenge.php');

            $config = <<<'PHP'
<?php

use Cosmira\Soda\Config\Soda;
use Cosmira\Soda\Config\RuleCatalog;

$target = __TARGET__;
$rules = array_values(array_filter(
    RuleCatalog::standard(),
    static fn ($rule): bool => $rule::class !== $target,
));

return Soda::configure()->withPaths([__DIR__.'/src'])->with($rules);
PHP;
            $config = str_replace('__TARGET__', var_export($introducedRule::class, true), $config);
            file_put_contents($sandbox.'/soda.php', $config);

            $command = implode(' ', [
                escapeshellarg(PHP_BINARY),
                escapeshellarg($root.'/soda'),
                'q',
                escapeshellarg($sourceDirectory),
                '--config='.escapeshellarg($sandbox.'/soda.php'),
                '--no-ansi',
            ]);
            exec($command, $output, $status);

            $this->assertSame(0, $status, basename($matches[0])." was caught before its rule existed:\n".implode("\n", $output));

            unlink($sourceDirectory.'/challenge.php');
            unlink($sandbox.'/soda.php');
            rmdir($sourceDirectory);
            rmdir($sandbox);
            unset($output);
        }
    }

    /** @return array<string, SodaRule> */
    private function rules(): array
    {
        return [
            'no_service_locator_calls'     => new NoServiceLocatorCalls,
            'no_global_state'              => new NoGlobalState,
            'no_environment_reads'         => new NoEnvironmentReads,
            'no_error_suppression'         => new NoErrorSuppression,
            'no_dynamic_invocation'        => new NoDynamicInvocation,
            'no_catch_all_exceptions'      => new NoCatchAllExceptions,
            'no_boolean_parameters'        => new NoBooleanParameters,
            'no_behavior_magic_methods'    => new NoBehaviorMagicMethods,
            'no_static_mutable_properties' => new NoStaticMutableProperties,
            'no_blocking_sleep'            => new NoBlockingSleep,
        ];
    }

    /** @return list<SodaRule> */
    private function iterationRules(): array
    {
        return [
            ...array_values($this->rules()),
            new NoDynamicInvocation,
            new NoBooleanParameters,
            new NoEnvironmentReads,
            new NoBlockingSleep,
            new NoStaticMutableProperties,
            new NoSingletonAccess,
            new NoReflection,
            new NoRuntimeHooks,
            new NoRuntimeHooks,
            new NoRuntimeCodeEvaluation,
            new NoEnvironmentReads,
            new NoProcessConfigurationMutation,
            new NoProcessConfigurationMutation,
            new NoProcessConfigurationMutation,
            new NoProcessConfigurationMutation,
            new NoDirectResponseGlobals,
            new NoDirectResponseGlobals,
            new NoDirectResponseGlobals,
            new NoSymbolTableMutation,
            new NoImplicitArguments,
            new NoDirectFilesystemMutation,
            new NoDirectFilesystemMutation,
            new NoDirectFilesystemMutation,
            new NoDirectFilesystemMutation,
            new NoSubprocessExecution,
            new NoSubprocessExecution,
            new NoSubprocessExecution,
            new NoSubprocessExecution,
            new NoNativeDeserialization,
            new NoRuntimeIncludes,
            new NoRuntimeTypeAliases,
            new NoDynamicProperties,
            new NoAnonymousClasses,
            new NoDynamicInvocation,
            new NoObjectCloning,
            new NoClosureRebinding,
            new NoClosureRebinding,
            new NoRuntimeContractProbes,
            new NoRuntimeContractProbes,
            new NoRuntimeContractProbes,
        ];
    }
}
