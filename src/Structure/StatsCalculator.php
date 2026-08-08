<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Structure;

use function array_sum;
use function max;
use function round;

/**
 * @internal
 */
final class StatsCalculator
{
    /**
     * @param array<string, mixed> $stats
     *
     * @return array{
     *   llocClasses: int,
     *   llocFunctions: int,
     *   llocGlobal: int,
     *   classLlocMin: int,
     *   classLlocAvg: int,
     *   classLlocMax: int,
     *   methodLlocMin: int,
     *   methodLlocAvg: int,
     *   methodLlocMax: int,
     *   averageMethodsPerClass: int,
     *   minimumMethodsPerClass: int,
     *   maximumMethodsPerClass: int,
     *   averageFunctionLength: int
     * }
     */
    public static function compute(array $stats, int $lloc): array
    {
        /** @var list<int> $classLines */
        $classLines = $stats['classLines'] ?? [];
        /** @var list<int> $methodLines */
        $methodLines = $stats['methodLines'] ?? [];
        /** @var list<int> $methodsPerClass */
        $methodsPerClass = $stats['methodsPerClass'] ?? [];
        /** @var list<int> $functionLines */
        $functionLines = $stats['functionLines'] ?? [];

        [$llocClasses, $llocFunctions] = self::scaleLloc(
            array_sum($methodLines),
            array_sum($functionLines),
            $lloc,
        );
        $llocGlobal = max(0, $lloc - $llocClasses - $llocFunctions);

        return [
            'llocClasses'              => $llocClasses,
            'llocFunctions'            => $llocFunctions,
            'llocGlobal'               => $llocGlobal,
            'classLlocMin'             => self::safeMin($classLines),
            'classLlocAvg'             => self::safeAvg($classLines),
            'classLlocMax'             => self::safeMax($classLines),
            'methodLlocMin'            => self::safeMin($methodLines),
            'methodLlocAvg'            => self::safeAvg($methodLines),
            'methodLlocMax'            => self::safeMax($methodLines),
            'averageMethodsPerClass'   => self::safeAvg($methodsPerClass),
            'minimumMethodsPerClass'   => self::safeMin($methodsPerClass),
            'maximumMethodsPerClass'   => self::safeMax($methodsPerClass),
            'averageFunctionLength'    => self::safeAvg($functionLines),
        ];
    }

    /**
     * @param list<int> $arr
     */
    private static function safeMin(array $arr): int
    {
        return collect($arr)->min() ?? 0;
    }

    /**
     * @param list<int> $arr
     */
    private static function safeMax(array $arr): int
    {
        return collect($arr)->max() ?? 0;
    }

    /**
     * @param list<int> $arr
     */
    private static function safeAvg(array $arr): int
    {
        return (int) round(collect($arr)->avg() ?? 0.0);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function scaleLloc(int $llocClasses, int $llocFunctions, int $lloc): array
    {
        $total = $llocClasses + $llocFunctions;
        if ($total <= $lloc || $total <= 0) {
            return [$llocClasses, $llocFunctions];
        }

        $scale = (float) $lloc / (float) $total;

        return [
            (int) round((float) $llocClasses * $scale),
            (int) round((float) $llocFunctions * $scale),
        ];
    }
}
