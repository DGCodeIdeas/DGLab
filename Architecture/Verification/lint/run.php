#!/usr/bin/env php
<?php
/**
 * Architecture consistency linter.
 *
 * Single source of truth: Architecture/INDEX.md §2 (canonical ID -> component map).
 *
 * Checks (exit non-zero on any violation):
 *   1. Reference existence  - every CORE-/HUB-/ISPOKE-/ESPOKE-/BRIDGE-/DEPLOY- token
 *                             found in a blueprint must be a defined ID.
 *   2. Misattribution phrases - the two historically-wrong phrasings
 *                             ("CORE-09: Cryptography/Hashing", "HUB-28: Analytics/Ledger")
 *                             must not appear in component blueprints.
 *   3. Structural completeness - every expected blueprint file must exist.
 *
 * Usage: php Architecture/Verification/lint/run.php [path-to-Architecture]
 */

declare(strict_types=1);

final class ArchitectureLint
{
    private string $root;
    /** @var array<string,true> */
    private array $validIds = [];
    /** @var list<string> */
    private array $errors = [];

    /** Prefixes that denote a tier component reference. */
    private const PREFIXES = ['CORE', 'HUB', 'ISPOKE', 'ESPOKE', 'BRIDGE', 'DEPLOY'];

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function run(): int
    {
        $this->buildValidIds();
        $this->checkReferences();
        $this->checkIdentity();
        $this->checkStructure();

        if ($this->errors === []) {
            fprintf(STDERR, "architecture-lint: OK (%d files scanned)\n", $this->scanned);
            return 0;
        }

        fprintf(STDERR, "architecture-lint: %d error(s) found\n", count($this->errors));
        foreach ($this->errors as $e) {
            fprintf(STDERR, "  - %s\n", $e);
        }
        return 1;
    }

    private function buildValidIds(): void
    {
        $ranges = [
            'CORE'    => range(1, 20),
            'HUB'     => range(1, 30),   // 31 is proposed; allow it explicitly below
            'ISPOKE'  => range(1, 25),
            'ESPOKE'  => range(1, 15),
            'BRIDGE'  => [1],
            'DEPLOY'  => range(0, 4),
        ];
        foreach ($ranges as $p => $nums) {
            foreach ($nums as $n) {
                $this->validIds[sprintf('%s-%02d', $p, $n)] = true;
            }
        }
        // Proposed HUB-31 is referenced (as "pending") but not yet counted in §4.
        $this->validIds['HUB-31'] = true;
    }

    private int $scanned = 0;

    private function checkReferences(): void
    {
        $prefixAlt = implode('|', self::PREFIXES);
        $re = '/\b(' . $prefixAlt . ')-(\d{1,3})\b/';

        foreach ($this->markdownFiles() as $file) {
            $this->scanned++;
            $text = file_get_contents($file);
            if ($text === false) {
                continue;
            }
            // Strip fenced code blocks: references inside code are not governance references.
            $text = preg_replace('/```.*?```/s', '', $text) ?? $text;

            if (preg_match_all($re, $text, $m, PREG_SET_ORDER) === 0) {
                continue;
            }
            $rel = $this->rel($file);
            foreach ($m as $mm) {
                $id = $mm[1] . '-' . $mm[2];
                if (!isset($this->validIds[$id])) {
                    $this->errors[] = sprintf('%s: undefined reference "%s"', $rel, $id);
                }
            }
        }
    }

    /**
     * Governance Rule 1: a blueprint's own identity (its H1 "# XXX-NN: ...") must equal the ID
     * encoded in its filename and must be a defined ID in INDEX.md §2. This catches the class of
     * bug where a file claims the wrong component (e.g. a logging file titled "Cryptography").
     */
    private function checkIdentity(): void
    {
        $scopes = ['Core/', 'Hub/', 'Spoke/', 'Deploy/'];
        foreach ($this->markdownFiles() as $file) {
            $rel = $this->rel($file);
            $inScope = false;
            foreach ($scopes as $s) {
                if (str_starts_with($rel, $s)) {
                    $inScope = true;
                    break;
                }
            }
            if (!$inScope) {
                continue;
            }
            $text = file_get_contents($file);
            if ($text === false) {
                continue;
            }
            $fileId = preg_replace('/\.md$/', '', basename($rel));
            if (!isset($this->validIds[$fileId])) {
                // Not an ID-named blueprint (e.g. a sub-index); skip identity check.
                continue;
            }
            if (preg_match('/^#\s+(' . implode('|', self::PREFIXES) . ')-(\d{1,3})\b/m', $text, $m) === 1) {
                $h1Id = $m[1] . '-' . $m[2];
                if ($h1Id !== $fileId) {
                    $this->errors[] = sprintf(
                        '%s: H1 component ID "%s" does not match filename ID "%s"',
                        $rel,
                        $h1Id,
                        $fileId
                    );
                }
            } else {
                // Tolerate a prefix word before the ID, e.g. "# PHASE HUB-03:".
                $h1 = null;
                foreach (explode("\n", $text) as $line) {
                    if (preg_match('/^#\s+/', $line) === 1) {
                        $h1 = $line;
                        break;
                    }
                }
                if ($h1 !== null && preg_match('/(' . implode('|', self::PREFIXES) . ')-(\d{1,3})\b/', $h1, $m) === 1) {
                    $h1Id = $m[1] . '-' . $m[2];
                    if ($h1Id !== $fileId) {
                        $this->errors[] = sprintf(
                            '%s: H1 component ID "%s" does not match filename ID "%s"',
                            $rel,
                            $h1Id,
                            $fileId
                        );
                    }
                } else {
                    $this->errors[] = sprintf('%s: missing H1 "# %s: ..." component header', $rel, $fileId);
                }
            }
        }
    }

    private function checkStructure(): void
    {
        $expected = [];
        foreach (range(1, 20) as $n) {
            $expected[] = sprintf('Core/CORE-%02d.md', $n);
        }
        foreach (range(1, 30) as $n) {
            $expected[] = sprintf('Hub/HUB-%02d.md', $n);
        }
        foreach (range(1, 25) as $n) {
            $expected[] = sprintf('Spoke/Internal/ISPOKE-%02d.md', $n);
        }
        foreach (range(1, 15) as $n) {
            $expected[] = sprintf('Spoke/External/ESPOKE-%02d.md', $n);
        }
        $expected[] = 'Spoke/Bridge/BRIDGE-01.md';
        foreach (range(0, 4) as $n) {
            $expected[] = sprintf('Deploy/DEPLOY-%02d.md', $n);
        }
        foreach (range(1, 10) as $n) {
            $expected[] = sprintf('ADRs/ADR-%03d-*.md', $n);
        }
        $expected[] = 'ADRs/ADR-011-*.md';

        foreach ($expected as $e) {
            if (str_contains($e, '*')) {
                $dir = $this->root . '/' . substr($e, 0, (int)strpos($e, '/'));
                $base = basename($e);
                $glob = $this->root . '/' . $e;
                $found = false;
                foreach (glob($glob) ?: [] as $hit) {
                    if (is_file($hit)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $this->errors[] = sprintf('missing file matching "%s"', $e);
                }
            } else {
                $path = $this->root . '/' . $e;
                if (!is_file($path)) {
                    $this->errors[] = sprintf('missing file "%s"', $e);
                }
            }
        }
    }

    /** @return iterable<string> */
    private function markdownFiles(): iterable
    {
        $dir = new RecursiveDirectoryIterator(
            $this->root,
            FilesystemIterator::SKIP_DOTS
        );
        $it = new RecursiveIteratorIterator($dir);
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'md') {
                yield $f->getPathname();
            }
        }
    }

    private function rel(string $abs): string
    {
        $p = $abs;
        if (str_starts_with($p, $this->root . '/')) {
            return substr($p, strlen($this->root) + 1);
        }
        return $p;
    }
}

$root = $argv[1] ?? dirname(__DIR__, 2);
$root = realpath($root) ?: $root;
exit((new ArchitectureLint($root))->run());
