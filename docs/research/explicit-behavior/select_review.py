"""Select reproducible lookalikes without executing any donor code."""
import hashlib
import json
import sys
from pathlib import Path

root = Path(sys.argv[1] if len(sys.argv) > 1 else '/tmp/soda-explicit-results')
output = Path(sys.argv[2] if len(sys.argv) > 2 else '/tmp/soda-explicit-review.json')
reports = [json.loads((root / f'{name}.json').read_text()) for name in
           ('soda', 'monolog', 'symfony-console', 'guzzle')]
selected = []
for rule in ('no_trivial_factories', 'no_repeated_compound_conditions'):
    remainder = []
    batch = []
    for report in reports:
        rows = [dict(row, project=report['project']['name'],
                     partition=report['project']['partition'])
                for row in report['nonfindings'] if row['rule'] == rule]
        rows.sort(key=lambda row: hashlib.sha256(
            f"{rule}:{row['file']}:{row['class']}".encode()).hexdigest())
        batch.extend(rows[:5])
        remainder.extend(rows[5:])
    batch.extend(remainder[:max(0, 20 - len(batch))])
    selected.extend(batch)
output.write_text(json.dumps(selected, indent=2) + '\n')
for index, row in enumerate(selected):
    print(index, row['rule'], row['project'], row['file'], row['class'])
    for method in row['methods']:
        print(method['source'])
