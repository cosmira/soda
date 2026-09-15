<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\Dependencies\FactoryHookContracts;
use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Reports methods that only forward construction arguments, regardless of decorative metadata.
 */
final class NoTrivialFactories extends Check
{
    /**
     * Preserve explicitly identified published entry points, never decorative source markers.
     *
     * @param list<string> $contracts Qualified Class::method names whose API must remain available.
     */
    public function __construct(private readonly array $contracts = []) {}

    /**
     * Report creation wrappers without changing their callers automatically.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $hooks = null;
        $contracts = array_fill_keys(array_map(strtolower(...), $this->contracts), true);
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Stmt\ClassLike::class) as $class) {
            if ($class->name === null) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                $creation = ForwardedConstruction::fromMethod($method);
                if (! $creation instanceof New_) {
                    continue;
                }

                $name = isset($class->namespacedName) ? $class->namespacedName->toString() : $class->name->toString();
                $identity = strtolower($name.'::'.$method->name->toString());
                $hooks ??= FactoryHookContracts::forFile($file);
                $isHook = $hooks->isRequired($name, $method->name->toString());
                if (isset($contracts[$identity]) || $isHook) {
                    continue;
                }

                yield new Violation(
                    rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                    method: $method->name->toString(), class: $name, line: $method->getStartLine(),
                    message: sprintf('%s::%s() only constructs %s and forwards all parameters unchanged. Consider constructing %s at the call site.',
                        $name, $method->name->toString(), $creation->class->toString(), $creation->class->toString()),
                );
            }
        }
    }

    /**
     * Identify this standard check in configuration and diagnostics.
     */
    public function id(): string
    {
        return 'no_trivial_factories';
    }

    /**
     * Reuse the parsed AST without collecting metrics.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }
}
