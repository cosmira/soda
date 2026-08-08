<?php

declare(strict_types=1);

namespace Bunnivo\Soda\Plugins;

use Bunnivo\Soda\Config\SodaPlugin;
use Bunnivo\Soda\Quality\Rule\AskThenTellChecker;
use Bunnivo\Soda\Quality\Rule\ClassesChecker;
use Bunnivo\Soda\Quality\Rule\ClassRules;
use Bunnivo\Soda\Quality\Rule\CommentedCodeChecker;
use Bunnivo\Soda\Quality\Rule\EmptyCatchChecker;
use Bunnivo\Soda\Quality\Rule\LayerMixingChecker;
use Bunnivo\Soda\Quality\Rule\LineLengthChecker;
use Bunnivo\Soda\Quality\Rule\LocChecker;
use Bunnivo\Soda\Quality\Rule\MethodOrderChecker;
use Bunnivo\Soda\Quality\Rule\MultilinePhpDocChecker;
use Bunnivo\Soda\Quality\Rule\NamespaceChecker;
use Bunnivo\Soda\Quality\Rule\ProjectChecker;
use Bunnivo\Soda\Quality\Rule\TodoCommentChecker;

/**
 * Structural quality rules: LOC, class/method size, smells, namespaces.
 *
 * Covers: max_file_loc, max_line_length, max_class_length, max_methods_per_class, max_arguments,
 *         max_properties, max_public_methods, max_dependencies, max_efferent_coupling,
 *         max_todo_fixme_comments, max_commented_out_code, max_empty_catch_blocks,
 *         max_ask_then_tell, max_layer_dominance, max_classes_per_file,
 *         max_namespace_depth, max_classes_per_namespace, max_classes_per_project.
 */
final class StructuralPlugin implements SodaPlugin
{
    #[\Override]
    public function checkers(): array
    {
        return [
            new LocChecker,
            new LineLengthChecker,
            new ClassesChecker,
            new ClassRules,
            new TodoCommentChecker,
            new CommentedCodeChecker,
            new EmptyCatchChecker,
            new AskThenTellChecker,
            new MethodOrderChecker,
            new MultilinePhpDocChecker,
            new LayerMixingChecker,
            new NamespaceChecker,
            new ProjectChecker,
        ];
    }
}
