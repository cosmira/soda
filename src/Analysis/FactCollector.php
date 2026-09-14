<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Parses one PHP file and extracts quality metrics, complexity, and per-method aggregates.
 */
final readonly class FactCollector
{
    /**
     * Reuse the PHP parser between files; visitors belong to a single collection.
     */
    private Parser $parser;

    /**
     * Use the standard PHP parser, or supply one for integration and instrumentation.
     */
    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * Parse once, collect the requested facts, and retain this file's AST for checks.
     *
     * @param list<string>|null $required
     *
     * @throws ParserException
     */
    public function collect(string $file, ?array $required = null): FileFacts
    {
        $source = @file_get_contents($file);
        throw_if($source === false, ParserException::class, 'Cannot read '.$file, 0);
        $nodes = $this->parse($source, $file);
        $lineMap = LogicalLineMap::fromSource($source);

        $metrics = AstFacts::collect($nodes, $lineMap, $required);
        $needsComments = $required === null || in_array('comments', $required, true);
        $commentIssues = $needsComments
            ? SourceCommentIssueScanner::scan($source)
            : ['todoFixme' => [], 'commentedCode' => []];
        $metrics['todoFixme'] = $commentIssues['todoFixme'];
        $metrics['commentedCode'] = $commentIssues['commentedCode'];

        return new FileFacts($file, $source, $nodes, $metrics);
    }

    /**
     * Parse PHP while preserving the source path and original parser error.
     *
     * @return list<Node>
     */
    private function parse(string $source, string $file): array
    {
        try {
            $nodes = $this->parser->parse($source);
        } catch (Error $error) {
            throw new ParserException(
                sprintf('Cannot parse %s: %s', $file, $error->getMessage()),
                $error->getCode(),
                $error,
            );
        }

        throw_if($nodes === null, ParserException::class, 'Cannot parse '.$file, 0);

        return $nodes;
    }
}
