<?php

declare(strict_types=1);

namespace Cosmira\Soda\Analysis;

use Cosmira\Soda\Analysis\Composition\ClassBehaviorFacts;
use Cosmira\Soda\Rules\Architecture\Cohesion;
use Cosmira\Soda\Rules\Complexity\CognitiveComplexity;
use Cosmira\Soda\Rules\Usage\ParameterInputFacts;
use Cosmira\Soda\Rules\Usage\UnusedMethodDeclarations;
use PhpParser\Node;

/**
 * Collect optional design facts after names and ordinary structural metrics are resolved.
 *
 * @phpstan-import-type FileMetrics from FileFacts
 */
trait CollectsDesignFacts
{
    /**
     * Collect optional design measurements without changing legacy structural counts.
     *
     * @param FileMetrics       $metrics
     * @param list<Node>        $nodes
     * @param list<string>|null $required
     *
     * @return FileMetrics
     */
    private static function designMetrics(array $metrics, array $nodes, ?array $required): array
    {
        if (self::isRequired($required, 'cognitive')) {
            $metrics = self::cognitiveMetrics($metrics, $nodes);
        }

        if (self::isRequired($required, 'parameterInputs')) {
            $metrics['parameterInputs'] = ParameterInputFacts::collect($nodes);
            $metrics['parameterContracts'] = InheritedParameterContracts::collect($nodes);
        }

        $metrics['classBehavior'] = self::isRequired($required, 'classBehavior') ? ClassBehaviorFacts::collect($nodes) : [];

        $metrics['privateState'] = self::isRequired($required, 'privateState') ? PrivateStateFacts::collect($nodes) : [];

        $metrics['interfaceUsage'] = self::isRequired($required, 'interfaceUsage') ? InterfaceFacts::collect($nodes) : [];

        $metrics['methodUsage'] = self::isRequired($required, 'methodUsage') ? UnusedMethodDeclarations::collect($nodes) : [];

        $metrics['cohesion'] = self::isRequired($required, 'cohesion') ? (new Cohesion)->collect($nodes) : [];

        return $metrics;
    }

    /**
     * Store named measurements once and keep anonymous scopes outside legacy rule rows.
     *
     * @param FileMetrics $metrics
     * @param list<Node>  $nodes
     *
     * @return FileMetrics
     */
    private static function cognitiveMetrics(array $metrics, array $nodes): array
    {
        $methods = $metrics['methods'];
        $extras = [];
        foreach ((new CognitiveComplexity)->collect($nodes) as $name => $cognitive) {
            if (isset($methods[$name])) {
                $methods[$name] += $cognitive;

                continue;
            }

            $extras[$name] = $cognitive;
        }

        $metrics['methods'] = $methods;
        $metrics['cognitiveExtras'] = $extras;

        return $metrics;
    }
}
