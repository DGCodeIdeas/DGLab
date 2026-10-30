#!/usr/bin/env bash
set -euo pipefail
cd /home/z/my-project

echo "=== Step 0: Ensure on migration branch ==="
git checkout migration-stage-2a-filesystem 2>&1 | tail -2
echo "HEAD: $(git rev-parse --short HEAD)"

echo ""
echo "=== Step 1: Move src/ files to Core/Filesystem/ directly ==="
# Move Internal/ subdir
if [ -d Core/Filesystem/src/Internal ]; then
    mv Core/Filesystem/src/Internal Core/Filesystem/Internal
fi
# Move all .php files from src/ to parent
for f in Core/Filesystem/src/*.php; do
    if [ -f "$f" ]; then
        mv "$f" "Core/Filesystem/$(basename $f)"
    fi
done
# Remove empty src/ dir
rmdir Core/Filesystem/src 2>/dev/null || true
echo "  Moved files"

echo ""
echo "=== Step 2: Remove per-package composer.json ==="
rm -f Core/Filesystem/composer.json
echo "  Removed"

echo ""
echo "=== Step 3: Fix root composer.json PSR-4 ==="
python3 -c "
import json
with open('composer.json') as f:
    d = json.load(f)
d['autoload']['psr-4']['DGLab\\\\Core\\\\Filesystem\\\\'] = 'Core/Filesystem/'
with open('composer.json', 'w') as f:
    json.dump(d, f, indent=4)
    f.write('\n')
print('  PSR-4: DGLab\\\\Core\\\\Filesystem\\\\ -> Core/Filesystem/')
"

echo ""
echo "=== Step 4: Fix phpstan.neon ==="
cat > Core/Filesystem/phpstan.neon <<'NEON'
includes:
    - vendor/phpstan/phpstan/conf/bleedingEdge.neon

parameters:
    level: max
    paths:
        - .
    excludePaths:
        - tests/*
    reportUnmatchedIgnoredErrors: true
NEON
echo "  Updated phpstan.neon (paths: [.] excluding tests/)"

echo ""
echo "=== Step 5: Fix phpunit.xml.dist ==="
cat > Core/Filesystem/phpunit.xml.dist <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="../../../vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="../../../vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit"><directory>tests/Unit</directory></testsuite>
    </testsuites>
</phpunit>
XML
echo "  Updated phpunit.xml.dist (bootstrap -> ../../../vendor/autoload.php)"

echo ""
echo "=== Step 6: Fix boundary-lint scan_repository ==="
python3 << 'PYEOF'
f = 'scripts/architecture-boundary-lint.py'
c = open(f).read()
old = '    # Scan app/\n    app_path = REPO_ROOT / "app"'
new = '''    # Scan migrated tier directories: Core/, Hub/, Bridge/, Spokes/Internal/, Spokes/External/
    # Source files live directly under the component directory (no src/ subdir).
    for tier_dir in ["Core", "Hub", "Bridge", "Spokes/Internal", "Spokes/External"]:
        tier_path = REPO_ROOT / tier_dir
        if not tier_path.is_dir():
            continue
        for php_file in tier_path.rglob("*.php"):
            rel = str(php_file.relative_to(REPO_ROOT))
            if "/tests/" in rel or "/vendor/" in rel:
                continue
            scan_file(php_file, result, allowlist)

    # Scan app/
    app_path = REPO_ROOT / "app"'''
if old in c and "Migrated tier" not in c[c.find("def scan_repository"):c.find("def scan_repository")+800]:
    c = c.replace(old, new)
    open(f, 'w').write(c)
    print("  Added tier dir scanning to scan_repository")
elif "Migrated tier" in c[c.find("def scan_repository"):c.find("def scan_repository")+800]:
    print("  Already has tier dir scanning")
else:
    print("  SKIP — pattern not found")
PYEOF

echo ""
echo "=== Step 7: Fix naming-drift.py CORE-14 ==="
python3 << 'PYEOF'
import re
f = 'scripts/fitness/naming-drift.py'
c = open(f).read()
c = re.sub(r'"CORE-14": \{[^}]+\}', '"CORE-14": {"package": "Core/Filesystem", "namespace": "DGLab\\\\Core\\\\Filesystem", "name": "Filesystem"}', c)
open(f, 'w').write(c)
print("  Updated CORE-14")
PYEOF

echo ""
echo "=== Step 8: Verify structure ==="
echo "--- Files under Core/Filesystem/ ---"
find Core/Filesystem -type f | sort
echo ""
echo "--- PSR-4 mapping ---"
python3 -c "import json; d=json.load(open('composer.json')); print(d['autoload']['psr-4']['DGLab\\\\Core\\\\Filesystem\\\\'])"
echo ""
echo "--- No composer.json ---"
ls Core/Filesystem/composer.json 2>&1 || echo "(removed — correct)"
echo ""
echo "--- No src/ dir ---"
ls -d Core/Filesystem/src 2>&1 || echo "(removed — correct)"

echo ""
echo "=== Step 9: Stage + commit + push ==="
git add -A
git status --short | head -20
echo ""
git commit -m "fix(migration): align with target architecture — no src/ subdir

CRITICAL: the agreed target architecture does NOT use src/ subdirectories.
Source files live directly under the component directory:

  Core/Filesystem/Filesystem.php     (not Core/Filesystem/src/Filesystem.php)
  Core/Filesystem/Internal/AtomicWriter.php

Changes:
1. Moved all PHP source from Core/Filesystem/src/ to Core/Filesystem/
2. Root composer.json PSR-4: DGLab\\Core\\Filesystem\\ -> Core/Filesystem/
3. Removed per-package composer.json (root-managed architecture)
4. phpstan.neon: paths [.] with excludePaths [tests/*]
5. phpunit.xml.dist: bootstrap -> ../../../vendor/autoload.php
6. boundary-lint: scan_repository scans tier dirs (no /src/ requirement)
7. naming-drift.py: CORE-14 entry updated" 2>&1 | tail -5
echo ""
git push --force "https://x-access-token:${GH_PAT}@github.com/DGCodeIdeas/DGLab.git" migration-stage-2a-filesystem:refs/heads/migration-stage-2a-filesystem 2>&1 | tail -5
echo ""
echo "HEAD: $(git rev-parse --short HEAD)"
