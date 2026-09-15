<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Cosmira\Soda\Analysis\FileFacts;
use Cosmira\Soda\Reporting\Violation;
use Cosmira\Soda\Rules\Check;

final class MethodsFollowCallOrder extends Check
{
    /**
     * Identify this rule in reports.
     */
    public function id(): string
    {
        return 'methods_follow_call_order';
    }

    /**
     * Use the already parsed syntax tree.
     *
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        return [];
    }

    /**
     * Check declaration order using the parsed file.
     *
     * @return iterable<Violation>
     */
    public function checkFile(FileFacts $file): iterable
    {
        $scanner = new MethodOrderNodeScanner();
        foreach ($scanner->scan($file) as $class) {
            yield from $this->violationsForClass($file->path, $class);
        }
    }

    /**
     * @return list<Violation>
     */
    private function violationsForClass(string $file, MethodOrderClassAnalysis $class): array
    {
        $violations = [];

        foreach ($class->methods as $caller) {
            array_push($violations, ...$this->violationsForCaller(
                $file,
                $class,
                $caller,
            ));
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function violationsForCaller(
        string $file,
        MethodOrderClassAnalysis $class,
        MethodOrderMethod $caller,
    ): array {
        $callsCount = count($caller->calls);
        if ($callsCount < 2) {
            return [];
        }

        $violations = [];
        $previousCall = null;
        $byName = $class->methodsByName();

        foreach ($caller->calls as $call) {
            if ($class->isOutOfOrder($previousCall, $call)) {
                $violations[] = $this->violation($file, $class, $caller, $byName[$previousCall], $byName[$call]);
            }

            $previousCall = $call;
        }

        return $violations;
    }

    /**
     * Construct a diagnostic with the applicable limits and source context.
     */
    private function violation(
        string $file,
        MethodOrderClassAnalysis $class,
        MethodOrderMethod $caller,
        MethodOrderMethod $current,
        MethodOrderMethod $previous,
    ): Violation {
        return new Violation(rule: $this->id(), file: $file, value: $current->line, threshold: $previous->line, method: $current->name, class: $class->name, line: $current->line, message: sprintf(
            'Consider declaring private helper %s() before %s(): their sole direct caller %s() uses that order',
            $current->name,
            $previous->name,
            $caller->name,
        ));
    }
}
