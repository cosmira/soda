<?php

declare(strict_types=1);

namespace Cosmira\Soda;

use Cosmira\Soda\Analysis\FactCollector;
use Cosmira\Soda\Analysis\ProjectFacts;
use Cosmira\Soda\Rules\Architecture\NoEmptyLocalInterfaces;
use Cosmira\Soda\Rules\Architecture\NoRedundantLocalInterfaces;
use Cosmira\Soda\Rules\Check;
use PHPUnit\Framework\TestCase;

final class LocalInterfaceRulesTest extends TestCase
{
    public function testEmptyInterfaceEvidenceAndDecorations(): void
    {
        $findings = $this->findings(new NoEmptyLocalInterfaces, ['<?php namespace App; #[Boundary] interface Marker { const LABEL = "marker"; }']);
        self::assertCount(1, $findings);
        self::assertSame('App\\Marker', $findings[0]->class);
        self::assertSame(1, $findings[0]->line);
        self::assertSame(1, $findings[0]->value);
        self::assertSame(0, $findings[0]->threshold);
    }

    public function testInheritedMethodsAcrossFilesAndAliases(): void
    {
        $source = ['<?php namespace Contracts; interface ParentContract { function run(); }', '<?php namespace App; use Contracts\\ParentContract as Base; interface Child extends Base {}'];
        self::assertSame([], $this->findings(new NoEmptyLocalInterfaces, $source));
    }

    public function testUnknownParentsRequireAnExplicitProtocol(): void
    {
        $source = ['<?php interface Marker extends ExternalProtocol {}'];
        self::assertCount(1, $this->findings(new NoEmptyLocalInterfaces, $source));
        self::assertSame([], $this->findings(new NoEmptyLocalInterfaces(['ExternalProtocol']), $source));
    }

    public function testEmptyParentCyclesDoNotProvideAContract(): void
    {
        self::assertCount(2, $this->findings(new NoEmptyLocalInterfaces, ['<?php interface A extends B {} interface B extends A {}']));
    }

    public function testImplementationAndImportDoNotConstituteConsumption(): void
    {
        $source = ['<?php namespace App; interface Work { function run(); } class Worker implements Work { function run() {} }', '<?php namespace Elsewhere; use App\\Work;'];
        self::assertCount(1, $this->findings(new NoRedundantLocalInterfaces, $source));
        self::assertSame([], $this->findings(new NoRedundantLocalInterfaces(['App\\Work']), $source));
    }

    public function testLocalAbsenceDoesNotJustifyDeletingAnExportedContract(): void
    {
        $source = ['<?php namespace Package; interface Printer { function print(string $message): void; }'];
        $findings = $this->findings(new NoRedundantLocalInterfaces, $source);
        self::assertCount(1, $findings);
        self::assertStringContainsString('in the analysed project', $findings[0]->message);
        self::assertStringContainsString('Declare external consumers in contracts', $findings[0]->message);
        self::assertStringContainsString('remove it only if it has no consumers', $findings[0]->message);
        self::assertSame([], $this->findings(new NoRedundantLocalInterfaces(['Package\\Printer']), $source));
    }

    public function testParameterAndRegistrationAreConsumersAcrossFiles(): void
    {
        $declaration = '<?php namespace App; interface Work { function run(); }';
        foreach (['function execute(Contract $work) {}', 'register(Contract::class);'] as $consumer) {
            self::assertSame([], $this->findings(new NoRedundantLocalInterfaces, [$declaration, '<?php namespace Other; use App\\Work as Contract; '.$consumer]));
        }
    }

    public function testSelfReferencesAndUnusedContractCyclesDoNotInventConsumers(): void
    {
        self::assertCount(1, $this->findings(new NoRedundantLocalInterfaces, ['<?php interface Node { function next(): Node; }']));
        $source = '<?php interface A { function next(): B; } interface B { function next(): A; }';
        self::assertCount(2, $this->findings(new NoRedundantLocalInterfaces, [$source]));
        self::assertSame([], $this->findings(new NoRedundantLocalInterfaces, [$source.' function consume(A $input) {}']));
        self::assertSame([], $this->findings(new NoRedundantLocalInterfaces(['A']), [$source]));
    }

    public function testOnlyConsumedChildrenMakeParentContractsUseful(): void
    {
        $source = '<?php interface Base { function run(); } interface Child extends Base {}';
        self::assertCount(2, $this->findings(new NoRedundantLocalInterfaces, [$source]));
        self::assertSame([], $this->findings(new NoRedundantLocalInterfaces, [$source.' function consume(Child $input) {}']));
    }

    public function testQualifiedPhpDocDeclarationConsumesInterfaceAcrossFiles(): void
    {
        $declaration = '<?php namespace App; interface Signer { function sign(); }';
        foreach ([
            '/** @var \\App\\Signer|null */ private $signer;',
            '/** @param \\App\\Signer $signer */ function useSigner($signer) {}',
            '/** @return \\App\\Signer */ function signer() {}',
        ] as $member) {
            self::assertSame([], $this->findings(new NoRedundantLocalInterfaces, [$declaration, '<?php class Consumer { '.$member.' }']));
        }
    }

    public function testProseAndDisconnectedPhpDocCycleDoNotInventConsumers(): void
    {
        $declaration = '<?php namespace App; interface Signer { function sign(); }';
        self::assertCount(1, $this->findings(new NoRedundantLocalInterfaces, [$declaration, '<?php /** Describes \\App\\Signer for readers. */ class Consumer {}']));
        $cycle = '<?php namespace App; interface Signer { /** @return \\App\\Signer */ function next(); }';
        self::assertCount(1, $this->findings(new NoRedundantLocalInterfaces, [$cycle]));
        self::assertSame([], $this->findings(new NoRedundantLocalInterfaces, [$cycle.' function useSigner(Signer $signer) {}']));
    }

    public function testTemplateBoundIsATypeReferenceButNotAnIndependentInterfaceConsumer(): void
    {
        $contract = '<?php namespace App; interface Abilities { function can(); }';
        self::assertSame([], $this->findings(new NoRedundantLocalInterfaces, [$contract, '<?php /** @template TToken of \\App\\Abilities */ trait Tokens {}']));
        self::assertCount(1, $this->findings(new NoRedundantLocalInterfaces, ['<?php namespace App; /** @template T of \\App\\Recursive */ interface Recursive {}']));
        self::assertCount(1, $this->findings(new NoRedundantLocalInterfaces, [$contract, '<?php /** @template T Documents \\App\\Abilities */ trait Tokens {}']));
    }

    private function findings(Check $rule, array $sources): array
    {
        $project = new ProjectFacts;
        foreach ($sources as $source) {
            $path = tempnam(sys_get_temp_dir(), 'soda-interface-');
            file_put_contents($path, $source);

            try {
                $project->add((new FactCollector)->collect($path, $rule->requiredAnalyses()));
            } finally {
                unlink($path);
            }
        }

        return iterator_to_array($rule->checkProject($project));
    }
}
