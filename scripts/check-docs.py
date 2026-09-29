"""Read-only structural verification of the maintained Markdown ticket library."""
from pathlib import Path
import re
import json

root = Path(__file__).resolve().parents[1]
docs = root / 'docs'
files = sorted(docs.rglob('*.md'))
errors = []
link_count = 0
words = 0
for f in files:
    text = f.read_text(encoding='utf-8')
    words += len(text.split())
    if not text.startswith('# '):
        errors.append(f'Missing H1: {f.name}')
    if '\ufffd' in text:
        errors.append(f'Replacement character: {f.name}')
    for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)', text):
        if target.startswith(('https://','http://','mailto:','#')):
            continue
        link_count += 1
        clean = target.split('#')[0].strip('<>')
        if not (f.parent / clean).exists():
            errors.append(f'Broken link in {f.name}: {target}')

tickets = sorted((docs/'tickets').glob('*.md'))
ids = {int(f.name[:3]):f for f in tickets}
if sorted(ids) != list(range(1,39)):
    errors.append('Ticket sequence is not exactly T001–T038')
graph={}
required=['Outcome','Dependencies and decisions','Context carried into this ticket',
          'Records, artifacts and ownership','Required implementation or research work',
          'Interfaces, permissions and user experience','Acceptance and verification',
          'Source/reference starting points','Boundaries and failure handling',
          'Completion record — fill after execution']
for n,f in ids.items():
    text=f.read_text(encoding='utf-8')
    if not text.startswith(f'# T{n:03} — '):
        errors.append(f'Wrong ticket heading: {f.name}')
    for heading in required:
        if f'## {heading}' not in text:
            errors.append(f'{f.name}: missing {heading}')
    line=next((x for x in text.splitlines() if x.startswith('- Prerequisites:')), '')
    deps=[int(x) for x in re.findall(r'\[T(\d{3})\]',line)]
    graph[n]=deps
    if any(x>=n or x not in ids for x in deps):
        errors.append(f'Invalid/non-earlier dependency: {f.name}: {deps}')
    if len(text.split())<500:
        errors.append(f'Ticket lacks expected context/detail (<500 words): {f.name}')
    for ref in re.findall(r'\bT(\d{3})\b',text):
        if int(ref) not in ids:
            errors.append(f'Unknown ticket reference: {f.name}: T{ref}')

def ancestors(n):
    result=set()
    for d in graph.get(n,[]):
        result.add(d)
        result.update(ancestors(d))
    return result
if ancestors(37)!=set(range(1,37)):
    errors.append(f'T037 does not transitively cover every launch predecessor: {set(range(1,37))-ancestors(37)}')
qa=(docs/'02-conversation-and-decisions.md').read_text(encoding='utf-8')
qids=[int(n) for n in re.findall(r'^### Q(\d{3})',qa,re.M)]
if sorted(qids)!=list(range(1,42)):
    errors.append('Q&A sequence missing or duplicated')
trace=(docs/'11-requirement-traceability.md').read_text(encoding='utf-8')
mapped=[int(n) for n in re.findall(r'^- Q(\d{3}):',trace,re.M)]
if sorted(mapped)!=list(range(1,42)):
    errors.append('Not all Q001–Q041 decisions mapped')
for d in range(1,6):
    if f'### D{d:03}' not in qa:
        errors.append(f'Missing explicit instruction D{d:03}')
scenario=(docs/'08-cross-module-scenarios.md').read_text(encoding='utf-8')
if len(re.findall(r'^- S\d+:',scenario,re.M))!=28:
    errors.append('Scenario register should contain S01–S28')
result=dict(status='PASS' if not errors else 'FAIL',markdown_files=len(files),
            numbered_tickets=len(tickets),q_and_a=len(qids),explicit_instructions=5,
            scenarios=28,local_links_checked=link_count,word_count=words,
            launch_dependency_coverage=len(ancestors(37)),errors=errors)
print(json.dumps(result,indent=2))
raise SystemExit(bool(errors))
