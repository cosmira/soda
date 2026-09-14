<?php

declare(strict_types=1);

namespace Cosmira\Soda\Config;

use Closure;
use Cosmira\Soda\Analysis\FactCatalog;
use Phplrt\Contracts\Parser\ParserInterface;
use Phplrt\Lexer\Token\Token;
use Phplrt\Parser\Exception\ParserRuntimeException;
use Phplrt\Source\StringSource;
use stdClass;

/** Compiles a validated expression once, without evaluating user-supplied PHP. */
final class RuleExpression
{
    /**
     * Reuses the immutable compiled grammar across rule instances.
     */
    private static ?ParserInterface $parser = null;

    /**
     * Selects the file, class or method rows evaluated by this predicate.
     */
    public readonly string $scope;

    /**
     * @var Closure(array): bool
     */
    private readonly Closure $predicate;

    /**
     * @var list<string>
     */
    private array $fields = [];

    /**
     * Initialize the configured values and collaborators for this instance.
     */
    public function __construct(private readonly string $source)
    {
        throw_if(strlen($source) > 8192, ConfigException::class, 'Rule expressions must not exceed 8192 bytes.');

        self::$parser ??= require __DIR__.'/../../resources/rules.php';

        try {
            $rule = self::$parser->parse(new StringSource($source));
        } catch (ParserRuntimeException $error) {
            if ($error->token instanceof Token) {
                throw $this->error($error->getMessage(), $error->token->offset);
            }

            throw new ConfigException($error->getMessage(), 0, $error);
        } catch (\JsonException $error) {
            throw new ConfigException('Invalid string in rule: '.$error->getMessage(), 0, $error);
        }

        $this->scope = $rule->scope;
        $this->predicate = $this->compile($rule->expression);
    }

    /**
     * Evaluate the compiled predicate against one complete row of rule facts.
     */
    public function isMatch(array $row): bool
    {
        return ($this->predicate)($row);
    }

    /**
     * @return list<string>
     */
    public function requiredAnalyses(): array
    {
        $definitions = FactCatalog::all()[$this->scope];
        $analyses = [];
        foreach ($this->fields as $field) {
            $definition = $definitions[$field];
            $analyses[] = $definition['analysis'];
        }

        return array_values(array_unique(array_filter($analyses, static fn (?string $analysis): bool => $analysis !== null)));
    }

    /**
     * Validate an operand pair once and compile its comparison or dependency glob.
     */
    private function comparison(stdClass $node): Closure
    {
        $field = $node->field;
        $operator = $node->operator;
        $value = $node->value;
        $type = $this->fieldType($node);
        $numeric = $type === 'number';
        $operators = match ($type) {
            'number'       => ['==', '!=', '>', '>=', '<', '<='],
            'string'       => ['==', '!=', 'matches'],
            'dependencies' => ['matches'],
        };
        $inArray = in_array($operator, $operators, true);
        if (! $inArray) {
            throw $this->error(sprintf("Operator '%s' is not supported for '%s'.", $operator, $field), $node->offset);
        }

        $valid = $numeric ? is_float($value) && is_finite($value) : is_string($value);
        if (! $valid) {
            throw $this->error(sprintf("Field '%s' requires a ", $field).($numeric ? 'finite number.' : 'string.'), $node->offset);
        }

        $this->fields[] = $field;

        return static function (array $row) use ($field, $operator, $value, $numeric): bool {
            throw_unless(array_key_exists($field, $row), \LogicException::class, sprintf("Missing rule fact '%s'.", $field));

            $actual = $numeric ? (float) $row[$field] : $row[$field];
            if ($operator === 'matches') {
                foreach ((array) $actual as $candidate) {
                    if (fnmatch($value, $candidate, FNM_NOESCAPE)) {
                        return true;
                    }
                }

                return false;
            }

            return match ($operator) {
                '==' => $actual === $value,
                '!=' => $actual !== $value,
                '>'  => $actual > $value,
                '>=' => $actual >= $value,
                '<'  => $actual < $value,
                '<=' => $actual <= $value,
            };
        };
    }

    /**
     * Validate a field against the selected scope and return its accepted value category.
     */
    private function fieldType(stdClass $node): string
    {
        $field = $node->field;
        $scope = FactCatalog::all()[$this->scope];
        $definition = $scope[$field] ?? null;
        if ($definition !== null) {
            return $definition['type'];
        }

        throw $this->error(sprintf("Unknown %s field '%s'.", $this->scope, $field), $node->offset);
    }

    /**
     * Translate a source byte offset into a rule diagnostic with line and column.
     */
    private function error(string $message, int $offset): ConfigException
    {
        $prefix = substr($this->source, 0, $offset);
        $line = substr_count($prefix, "\n") + 1;
        $newline = strrpos($prefix, "\n");
        $column = $offset - ($newline === false ? -1 : $newline);

        return new ConfigException(sprintf('Rule at line %d, byte column %d: %s', $line, $column, $message));
    }

    /**
     * Compile grammar-validated expression nodes into reusable predicates.
     */
    private function compile(stdClass $node): Closure
    {
        if (isset($node->field)) {
            return $this->comparison($node);
        }

        $children = array_map($this->compile(...), $node->children);
        [$operand] = $children;

        if ($node->operator === 'not') {
            return static fn (array $row): bool => ! $operand($row);
        }

        // AND stops at the first false child; OR stops at the first true child.
        $expected = $node->operator === 'and';

        return static function (array $row) use ($children, $expected): bool {
            foreach ($children as $child) {
                $actual = $child($row);
                if ($actual !== $expected) {
                    return ! $expected;
                }
            }

            return $expected;
        };
    }
}
