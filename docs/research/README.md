# Research

This directory contains studies, pinned source corpora, review records, and
reproduction tools. Their findings describe the recorded versions. Use the
[rule reference](../rules/README.md) for current behavior and configuration.

Research hypotheses are not adopted quality rules. Research checks collect
evidence separately from violations and do not fail the quality check. See the
[research report schema](../QUALITY_REPORT_JSON.md#separate-research-json).

## Studies and corpora

- [Readability calibration](readability-calibration/README.md) compares policies against Soda and pinned Laravel sources.
- [Explicit behavior](explicit-behavior/README.md) records source cases and counterexamples.
- [Laravel audit](laravel-audit/README.md) records findings reviewed against framework sources.
- [Package audit](laravel-packagist/README.md) records findings across installed packages.
- [Sandbox audit](sandbox-audit/README.md) records a pinned application review.

## Methods

- [Rule research](../RULE_RESEARCH.md) records selection criteria and adoption decisions.
- [Research metrics](../RESEARCH_METRICS.md) describes cognitive complexity, cohesion, and measurement limits.

Pinned corpora, frozen detector snapshots, and reproduction tools support the
verification tests. Preserve their recorded scope and limitations when reviewing
the results.
