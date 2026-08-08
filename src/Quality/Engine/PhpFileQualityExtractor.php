<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Engine;

use Bunnivo\Soda\Breathing\BreathingAnalyser;
use Bunnivo\Soda\Exception\ParserException;
use Bunnivo\Soda\Quality\Ast\QualityAstPipeline;
use Bunnivo\Soda\Quality\Support\SourceCommentIssueScanner;

use function file_get_contents;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use SebastianBergmann\Complexity\ComplexityCollection;

use function sprintf;
use function substr_count;

/**
 * Parses one PHP file and extracts quality metrics, complexity, and per-method aggregates.
 */
final class PhpFileQualityExtractor
{
    /**
     * @psalm-param non-empty-string $file
     *
     * @throws ParserException
     * @throws Error
     *
     * @psalm-return array{
     *   metrics: array,
     *   complexity: ComplexityCollection,
     *   nesting: array<string, array{depth: int, line: int}>,
     *   returns: array<string, int>,
     *   booleanConditions: array<string, list<array{line: int, count: int}>>,
     *   tryCatch: array<string, int>
     * }
     */
    public function extract(string $file): array
    {
        $source = file_get_contents($file);

        throw_if($source === false, ParserException::class, 'Cannot read '.$file, 0);

        $lines = substr_count($source, "\n");
        if ($lines === 0 && $source !== '') {
            $lines = 1;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $nodes = $parser->parse($source);
        } catch (Error $error) {
            throw new ParserException(
                sprintf('Cannot parse %s: ', $file).$error->getMessage(),
                $error->getCode(),
                $error,
            );
        }

        throw_if($nodes === null, ParserException::class, 'Cannot parse '.$file, 0);

        $traverser = new NodeTraverser();
        $pipeline = QualityAstPipeline::create($lines);
        $pipeline->attachTo($traverser);

        $traverser->traverse($nodes);

        $visitorBundle = $pipeline->visitors()->astVisitorBundle;
        $primaryScanGroup = $visitorBundle->structuralScan;
        $flowScanGroup = $visitorBundle->flowBranchScan;
        $couplingNamingGroup = $visitorBundle->couplingScan;

        $metrics = $primaryScanGroup->structureScan->result();

        $classes = $metrics['classes'];

        foreach ($couplingNamingGroup->efferentCouplingVisitor->result() as $className => $ce) {
            if (isset($classes[$className])) {
                $entry = $classes[$className];
                $entry['efferent_coupling'] = $ce;
                $classes[$className] = $entry;
            }
        }

        $metrics['classes'] = $classes;

        $metrics['file_loc'] = $lines;
        $breathing = BreathingAnalyser::analyse($source, $nodes);
        $metrics['breathing'] = $breathing->toArray();
        $metrics['naming'] = $couplingNamingGroup->namingVisitor->result();
        $commentIssues = SourceCommentIssueScanner::scan($source);
        $metrics['todoFixme'] = $commentIssues['todoFixme'];
        $metrics['commentedCode'] = $commentIssues['commentedCode'];
        $metrics['emptyCatches'] = $flowScanGroup->emptyCatchScan->result();
        $metrics['askThenTell'] = $flowScanGroup->tellDontAskScan->result();

        return [
            'metrics'           => $metrics,
            'complexity'        => $primaryScanGroup->cyclomaticScan->result(),
            'nesting'           => $primaryScanGroup->controlNesting->result(),
            'returns'           => $flowScanGroup->returnStmtScan->result(),
            'booleanConditions' => $flowScanGroup->booleanCondScan->result(),
            'tryCatch'          => $flowScanGroup->catchBlockScan->result(),
        ];
    }
}
