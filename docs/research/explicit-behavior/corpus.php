<?php

declare(strict_types=1);

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Research\ExplicitBehavior\NoRepeatedCompoundConditions;
use Cosmira\Soda\Research\ExplicitBehavior\NoTrivialFactories;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

require dirname(__DIR__, 3).'/vendor/autoload.php';
require __DIR__.'/NoTrivialFactories.php';
require __DIR__.'/NoRepeatedCompoundConditions.php';

// Never execute or autoload donor source. Parse each file once, then discard its AST.
$partition = $argv[1] ?? 'discovery';
$donors = $argv[2] ?? '/tmp/soda-corpus';
$output = $argv[3] ?? '/tmp/soda-explicit-results';
if (! in_array($partition, ['discovery', 'held-out'], true)) {
    throw new InvalidArgumentException('Choose discovery or held-out.');
}
$manifest = json_decode(file_get_contents(__DIR__.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($manifest['detectors'] as $file => $hash) {
    if (hash_file('sha256', __DIR__.'/'.$file) !== $hash) {
        throw new RuntimeException('Frozen detector changed: '.$file);
    }
}
if (! is_dir($output)) {
    mkdir($output, 0700, true);
}
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$finder = new NodeFinder;
$rules = [new NoTrivialFactories, new NoRepeatedCompoundConditions];
foreach ($manifest['projects'] as $project) {
    if ($project['partition'] !== $partition) {
        continue;
    }
    $root = $project['name'] === 'soda' ? dirname(__DIR__, 3) : $donors.'/'.$project['name'];
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$project['root'], FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if ($file->isFile() && $file->getExtension() === 'php'
            && ! preg_match('~(^|/)(Tests|tests|Resources|vendor)(/|$)~', $relative)) {
            $files[$relative] = $file->getPathname();
        }
    }
    ksort($files);
    $hashes = [];
    $findings = [];
    $nonfindings = [];
    foreach ($files as $relative => $path) {
        $source = file_get_contents($path);
        $hashes[$relative] = hash('sha256', $source);
        $nodes = (new NodeTraverser(new NameResolver))->traverse($parser->parse($source) ?? []);
        $facts = new FileFacts($relative, $source, $nodes, []);
        $classFindings = [];
        foreach ($rules as $rule) {
            foreach ($rule->checkFile($facts) as $violation) {
                $findings[] = $violation->toArray();
                $classFindings[$rule->id()][$violation->class] = true;
            }
        }
        $lines = explode("\n", $source);
        foreach ($finder->findInstanceOf($nodes, Stmt\Class_::class) as $class) {
            if ($class->name === null) {
                continue;
            }
            $name = $class->namespacedName->toString();
            $factoryMethods = [];
            $conditionMethods = [];
            foreach ($class->getMethods() as $method) {
                $excerpt = implode("\n", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
                $entry = ['method' => $method->name->toString(), 'line' => $method->getStartLine(), 'source' => $excerpt];
                if ($method->name->toLowerString() === 'create' || $finder->findFirstInstanceOf($method->stmts ?? [], Expr\New_::class) !== null) {
                    $factoryMethods[] = $entry;
                }
                if ($finder->findFirst($method->stmts ?? [], fn ($node): bool => $node instanceof Expr\BinaryOp\BooleanAnd || $node instanceof Expr\BinaryOp\BooleanOr) !== null) {
                    $conditionMethods[] = $entry;
                }
            }
            foreach (['no_trivial_factories' => $factoryMethods, 'no_repeated_compound_conditions' => $conditionMethods] as $id => $methods) {
                if ($methods === [] || isset($classFindings[$id][$name])) {
                    continue;
                }
                $nonfindings[] = ['rule' => $id, 'file' => $relative, 'class' => $name,
                    'line'               => $class->getStartLine(), 'source_sha256' => $hashes[$relative],
                    'declaration'        => trim($lines[$class->getStartLine() - 1]),
                    'methods'            => $methods];
            }
        }
    }
    $result = ['project' => $project, 'file_count' => count($files), 'source_tree_sha256' => hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR)),
        'findings'       => $findings, 'nonfindings' => $nonfindings];
    file_put_contents($output.'/'.$project['name'].'.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    printf("%s: %d files, %d findings, %d lookalikes\n", $project['name'], count($files), count($findings), count($nonfindings));
}
