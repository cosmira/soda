# Continuous integration

- [Run the quality check](#run-the-quality-check)
- [Use GitHub Actions](#use-github-actions)
- [Save a JSON report](#save-a-json-report)
- [Keep research separate](#keep-research-separate)

## Run the quality check

Commit your project's `soda.php` so developers and CI use the same paths and rules.
See [Configuration](SODA_PHP_CONFIG.md) to create it.

After checking out the project and setting up PHP, run:

```bash
composer install --prefer-dist --no-interaction --no-progress
vendor/bin/soda
```

Install development dependencies. An install with `--no-dev` omits Soda when it
is declared in `require-dev`.

The check exits with `0` when it passes. It exits with `1` for violations or a
configuration or input error. Let your CI runner use that exit status to fail the
job; do not ignore it.

If you have no configuration, pass a source directory instead:

```bash
vendor/bin/soda quality src/
```

## Use GitHub Actions

This job checks a project with a committed `soda.php`:

```yaml
name: Code quality

on: [push, pull_request]

permissions:
  contents: read

jobs:
  soda:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v7
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
          tools: composer:v2
      - run: composer install --prefer-dist --no-interaction --no-progress
      - run: vendor/bin/soda --no-ansi
```

Choose a PHP version supported by your application and its dependencies.

## Save a JSON report

Create the output directory before requesting a report:

```bash
mkdir -p build
vendor/bin/soda --report-type=json --report-file=build/soda-quality.json
```

The report contains the quality result and individual violations. Requesting a
report does not change the command's exit status. Configure your CI provider to
collect the file even when the quality check fails.

See [JSON reports](QUALITY_REPORT_JSON.md) for the schema. Configuration and input
errors can stop the check before a report is written.

## Keep research separate

Research checks produce evidence for review without failing the quality check.
A check can be enabled by default and still be nongating. Consult the
[rule reference](rules/README.md) for both properties; the research origin of a
metric alone does not determine its effect on the exit status.
If your configured rules include research checks, save their output separately:

```bash
mkdir -p build
vendor/bin/soda \
    --report-type=json --report-file=build/soda-quality.json \
    --research-json=build/soda-research.json
```

The [research report schema](QUALITY_REPORT_JSON.md#separate-research-json)
describes partial results, analysis limits, and coverage. Use the
[research index](research/README.md) for studies and reproduction tools.
