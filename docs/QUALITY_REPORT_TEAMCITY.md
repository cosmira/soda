# Report TeamCity

Use `--report-type=teamcity` to print TeamCity service messages next to the human
report:

```bash
vendor/bin/soda quality src/ --report-type=teamcity
```

TeamCity reads service messages from the build log, so they print to stdout;
pass `--report-file=` to archive them to a file instead. Wrap the check inside
one test suite: every violation
becomes a failed test named `<file>:<line> <rule>` and appears in the Tests tab
with the finding summary, the owning method or class, and the remediation
advice:

```
##teamcity[testSuiteStarted name='soda quality']
##teamcity[testStarted name='src/Flow.php:12 max_effective_class_length']
##teamcity[testFailed name='src/Flow.php:12 max_effective_class_length' message='Source lines including traits: 512 (max 500)' details='…|n…']
##teamcity[testFinished name='src/Flow.php:12 max_effective_class_length']
##teamcity[testSuiteFinished name='soda quality']
```

A passing run prints only the suite messages. Message values use TeamCity
escaping (`||`, `|'`, `|n`, `|r`, `|[`, `|]`), so findings with quotes,
brackets or multiline advice stay intact.

The exit status stays `0` without violations and `1` with violations, exactly
as without the flag, so the build fails the same way. To archive
machine-readable results alongside, write the JUnit XML file below or run a
second pass with `--report-type=json --report-file=`; research evidence stays
out of the service messages and is written separately
by `--research-json=`.

## Import a JUnit XML report

TeamCity parses service messages only from the build log. To deliver the same
results as a file, write a JUnit XML report — the file format PHPUnit writes
with `--log-junit=` and the TeamCity "XML report processing" build feature
imports into the Tests tab:

```bash
vendor/bin/soda quality src/ --report-type=junit --report-file=build/soda-junit.xml
```

The file wraps every violation in a failed `<testcase>` of the `soda quality`
suite with the same names, summaries and advice as the service messages, so
both channels render identically:

```xml
<testsuite name="soda quality" tests="1" failures="1" errors="0">
  <testcase name="src/Flow.php:12 max_effective_class_length" file="src/Flow.php" line="12">
    <failure type="max_effective_class_length" message="Source lines including traits: 512 (max 500)">…</failure>
  </testcase>
</testsuite>
```

The exit status and the service messages stay unchanged; the file report is an
additional channel, not a replacement.
