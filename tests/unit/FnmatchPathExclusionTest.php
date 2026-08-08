<?php

declare(strict_types=1);

namespace Bunnivo\Soda;

use Bunnivo\Soda\Plugins\PathExclusion\FnmatchPathExclusion;
use PHPUnit\Framework\TestCase;

final class FnmatchPathExclusionTest extends TestCase
{
    public function testExcludesWhenPathIsUnderPrefix(): void
    {
        $dir = sys_get_temp_dir().'/soda-fnmatch-prefix-'.uniqid();
        mkdir($dir, 0700, true);
        $file = $dir.'/X.php';
        touch($file);

        $policy = new FnmatchPathExclusion([], [$dir]);

        $this->assertTrue($policy->isExcludedPath($file));

        unlink($file);
        rmdir($dir);
    }

    public function testExcludesWhenBasenameMatchesPattern(): void
    {
        $dir = sys_get_temp_dir().'/soda-fnmatch-base-'.uniqid();
        mkdir($dir, 0700, true);
        $file = $dir.'/FooRequest.php';
        touch($file);

        $policy = new FnmatchPathExclusion(['*Request.php'], []);

        $this->assertTrue($policy->isExcludedPath($file));

        unlink($file);
        rmdir($dir);
    }
}
