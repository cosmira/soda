#!/usr/bin/env bash
set -euo pipefail

vale_bin=$1
probe='Simply utilize SODA in order to commence.'

for page in README.md docs/README.md docs/research/README.md; do
    status=0
    printf '%s\n' "$probe" | "$vale_bin" --no-global --path="$page" > /dev/null || status=$?
    test "$status" -eq 1
done

printf '%s\n' "$probe" | "$vale_bin" --no-global --path=docs/research/historical-study.md > /dev/null
