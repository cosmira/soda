"""Select deterministic, stratified finding and non-finding samples (no judgments)."""
import hashlib
import json
import sys
from pathlib import Path

root = Path(sys.argv[1] if len(sys.argv) > 1 else '/tmp/soda-corpus-results')
partition = sys.argv[2] if len(sys.argv) > 2 else 'discovery'
quotas = {'discovery': {'soda': 20, 'laravel': 40, 'monolog': 10}, 'held-out': {'symfony-console': 15, 'guzzle': 15}}[partition]
seed = 'soda-practical-design-2026-09-14'
selected = []
nonfindings = []
for project, quota in quotas.items():
    report = json.loads((root / (project + '.json')).read_text())
    def key(row):
        return hashlib.sha256((seed + project + row['file'] + str(row['line']) + row.get('rule', row.get('name', ''))).encode()).hexdigest()
    groups = {}
    for row in report['violations']:
        groups.setdefault(row['rule'], []).append(dict(row, project=project))
    for group in groups.values():
        group.sort(key=key)
    # Round robin by rule; when a rare rule runs out, fill from remaining groups.
    chosen = []
    while len(chosen) < quota and any(groups.values()):
        for rule in sorted(groups):
            if groups[rule] and len(chosen) < quota:
                chosen.append(groups[rule].pop(0))
    selected.extend(chosen)
    positive = {(r['file'], r['line']) for r in report['violations']}
    candidates = [dict(r, project=project, rule='non-finding') for r in report['callables'] if (r['file'], r['line']) not in positive]
    candidates.sort(key=key)
    nonfindings.extend(candidates[:5])
for prefix, rows in [('D' if partition == 'discovery' else 'H', selected), ('N' if partition == 'discovery' else 'M', nonfindings)]:
    for index, row in enumerate(rows, 1):
        row['review_id'] = f'{prefix}{index:03}'
    (root / (partition + ('-sample.json' if prefix in ['D', 'H'] else '-nonfindings.json'))).write_text(json.dumps(rows, indent=2) + '\n')
print(f'{partition}: {len(selected)} findings, {len(nonfindings)} non-findings selected; no judgment has been assigned.')
