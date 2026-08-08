# Soda

Write PHP code that **stays clean, maintainable, and architecturally solid**.

Soda helps you **enforce quality rules automatically**, so you don’t have to hunt for hidden issues in huge classes or 500-line methods. Turn code metrics into actionable rules that make sense for your project.

Even with AI-generated code, errors slip through. **Soda catches them first**, keeping your code consistent, safe, and review-ready—before it ever reaches a pull request.

> **Stop trusting prompts. Start trusting PHP Quality Gates.**

## Installation

Requires PHP 8.3.18 or higher.

```bash
composer require bunnivo/soda --dev
```

For local development:

```bash
git clone https://github.com/tabuna/soda.git
cd soda
composer install
```

## Usage

### Quality checks

Create a configuration file:

```bash
php soda init
```

It creates `soda.php` in your project root:

```php
<?php

use Bunnivo\Soda\Config\Soda;
use Bunnivo\Soda\Plugins\Rules\Structural\MaxFileLoc;

return Soda::configure()
    ->withPaths([
        'src/',
    ])
    ->with([
        new MaxFileLoc(700),
    ]);
```

Run the quality check:

```bash
php soda quality
```

Example output:

```
Soda Quality
------------------------------------------------------------

2 issues

src/Services/UserService.php
  ! Line —
    Properties per class: 18 (max 15)

src/Controllers/UserController.php
  ! Line —
    Methods per class: 22 (max 20)

------------------------------------------------------------

[FAIL] 2 issues
```

### Options

| Option           | Description                   |
|------------------|-------------------------------|
| `--config=`      | Path to `soda.php`            |
| `--report-json=` | Export report to JSON         |
| `--suffix=`      | File suffix (default: `.php`) |
| `--exclude=`     | Exclude paths (repeatable)    |

### Disabling rules

Remove a rule from the `with([...])` list to disable it.

### Project metrics

Measure project size and structure without a quality config:

```bash
php soda analyse src
```

### Documentation

| Document                                                           | Content                                                                     |
|--------------------------------------------------------------------|-----------------------------------------------------------------------------|
| [Documentation](docs/README.md)                                    | Start here for config, rules, reports, and design notes                     |
| [Project Flow](docs/PROJECT_FLOW.md)                               | Mermaid runtime flow, rule authoring flow, and cleanup matrix               |
| [Config](docs/SODA_PHP_CONFIG.md)                                  | Root `soda.php`, paths, rules, custom rules                                 |
| [Structural Metrics](docs/STRUCTURAL_METRICS.md)                   | Size, dependencies, structure — good/bad examples, possible values          |
| [Complexity & Readability](docs/COMPLEXITY_READABILITY_METRICS.md) | Cyclomatic complexity, nesting, breathing metrics — examples, config ranges |
| [Breathing Metrics](docs/BREATHING_METRICS.md)                     | Breathing metrics overview                                                  |

## CI Integration

**Run Soda after** linters and static analyzers. It checks structure and readability — not syntax or types.

Recommended order:

1. **Rector** — refactoring
2. **PHPStan** (or Psalm) — static analysis
3. **Laravel Pint** (or similar) — formatting
4. **Soda** — quality gates (structure, complexity, breathing)

```yaml
- name: Install composer dependencies
  run: composer install --no-interaction

- name: Run other tools
  run: echo "This is a placeholder for other tools"

- name: Run Soda quality check
  run: php soda quality
```

## Contributing

Thank you for considering contributing to Soda! Please feel free to submit a Pull Request.

## License

Soda is open-sourced software licensed under the [BSD 3-Clause License](LICENSE).
