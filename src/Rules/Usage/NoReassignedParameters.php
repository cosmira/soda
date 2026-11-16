<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Usage;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Contracts\Check;
use Cosmira\Soda\Reporting\Rule;
use Cosmira\Soda\Reporting\Violation;
use InvalidArgumentException;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;

/**
 * Optionally rejects direct rebinding of by-value parameters while preserving object mutation APIs.
 */
#[Rule(
    label: 'Rebound input parameters:',
    advice: 'Keep original input bindings separate from transformed values; a narrow entry normalization is accepted.',
)]
final class NoReassignedParameters extends Check
{
    /**
     * Configure exact parameter names allowed to be rebound.
     *
     * @param list<string> $ignoredParameters Names without the dollar prefix.
     */
    public function __construct(
        /**
         * Explicit exceptions for intentional input normalization.
         */
        private readonly array $ignoredParameters = [],
        /**
         * Accept a leading normalization of the same input using known scalar operations.
         */
        private readonly bool $allowInputNormalization = true,
    ) {
        foreach ($ignoredParameters as $name) {
            throw_if(! is_string($name) || $name === '' || str_starts_with($name, '$'), InvalidArgumentException::class, 'Ignored parameter names must be non-empty strings without a dollar prefix.');
        }
    }

    /**
     * Identify the parameter-rebinding policy.
     */
    public function id(): string
    {
        return 'no_reassigned_parameters';
    }

    /**
     * Report direct writes with parameter names and original source locations.
     */
    public function checkFile(FileFacts $file): iterable
    {
        $visitor = new ParameterReassignmentVisitor($this->ignoredParameters, $this->allowInputNormalization, $this->declaredFunctions($file));
        (new NodeTraverser($visitor))->traverse($file->nodes);
        foreach ($visitor->writes() as $write) {
            yield new Violation(
                rule: $this->id(), file: $file->path, value: 1, threshold: 0,
                line: $write['line'],
                message: 'Parameter $'.$write['name'].' is rebound. Keep the input name for the original value and give a transformed value its own name, or configure intentional rebinding explicitly.',
            );
        }
    }

    /**
     * Namespaced calls cannot use the builtin exception when this source declares their target.
     *
     * @return list<string>
     */
    private function declaredFunctions(FileFacts $file): array
    {
        $functions = [];
        foreach ((new NodeFinder)->findInstanceOf($file->nodes, Function_::class) as $function) {
            $functions[] = strtolower($function->namespacedName?->toString() ?? $function->name->toString());
        }

        return $functions;
    }
}
