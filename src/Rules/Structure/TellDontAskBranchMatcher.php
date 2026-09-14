<?php

declare(strict_types=1);

namespace Cosmira\Soda\Rules\Structure;

use Illuminate\Support\Str;

use function str_contains;

/**
 * @internal
 */
final class TellDontAskBranchMatcher
{
    /**
     * Return current class name for the supplied analysis context.
     */
    public static function currentClassName(?string $methodName): ?string
    {
        $isClassMethod = $methodName !== null && str_contains($methodName, '::');

        return $isClassMethod
            ? Str::before($methodName, '::')
            : null;
    }

    /**
     * @param list<array{receiver: string, method: string}> $questions
     * @param array{receiver: string, method: string}       $command
     *
     * @return array{receiver: string, method: string}|null
     */
    public static function firstMatch(array $questions, array $command): ?array
    {
        foreach ($questions as $question) {
            if ($question['receiver'] !== $command['receiver']) {
                continue;
            }

            if ($question['method'] === $command['method']) {
                continue;
            }

            return $question;
        }

        return null;
    }
}
