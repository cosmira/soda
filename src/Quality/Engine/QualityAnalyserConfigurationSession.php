<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Quality\Engine;

use Bunnivo\Soda\Plugins\StandardPlugin;
use Bunnivo\Soda\Quality\Config\ConfigResolver;
use Bunnivo\Soda\Quality\ConfigException;
use Bunnivo\Soda\Quality\QualityEngine;

/**
 * @internal
 */
final class QualityAnalyserConfigurationSession
{
    /**
     * @psalm-param list<non-empty-string> $files
     *
     * @throws ConfigException
     */
    public static function engineForFiles(array $files, ?string $configPath): QualityEngine
    {
        $config = ConfigResolver::resolveConfig($files, $configPath);

        return QualityEngine::create(
            $config,
            [
                ...($config->noBuiltinRules ? [] : (new StandardPlugin)->checkers()),
                ...$config->pluginCheckers,
            ],
        );
    }
}
