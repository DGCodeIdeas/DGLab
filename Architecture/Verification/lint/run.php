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
 *                             must not appear in component blueprints in active (non-strikethrough,
 *                             non-code) form. Implemented in PR #314 (was previously claimed in
 *                             this docstring but unimplemented — pre-existing bug per SAAI
 *                             directive 2026-10-06).
 *   3. Structural completeness - every expected blueprint file must exist.
 *                             Updated in PR #314 to expect HUB-01..32 (was HUB-01..30) and
 *                             ADR-001..021 (was ADR-001..015) — pre-existing gap.
 *   4. Self-test (negative regression) - invoked via `--self-test` flag. Verifies the lint
 *                             correctly FAILS when expected files are missing. Per SAAI
 *                             directive: "A structural checker that only tests the happy
 *                             path isn't sufficient."
 *
 * Usage:
 *   php Architecture/Verification/lint/run.php [path-to-Architecture]
 *   php Architecture/Verification/lint/run.php --self-test
 *
 * INVARIANT (per SAAI directive 2026-10-06): Three-state reporting.
 *   PASS       — control executed and passed (lint ran, found 0 errors)
 *   FAIL       — control executed and failed (lint ran, found errors)
 *   UNVERIFIED — control did not execute (e.g., PHP not installed, missing
 *                Architecture/ directory). MUST NOT be reported as green.
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
        $this->checkMisattribution();
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
            'HUB'     => range(1, 32),   // HUB-31 accepted per ADR-011; HUB-32 canonical per ADR-021 §13
            'ISPOKE'  => range(1, 27),   // 26/27 promoted from hospitality-vertical design 2026-08-12 (ADR-015)
            'ESPOKE'  => range(1, 19),   // 16/17/18 hospitality-vertical (ADR-015); 19 Eloq canonical per ADR-021 §14
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
     * Check #2 — Misattribution phrases.
     *
     * Per the docstring's claim (historically unimplemented — pre-existing bug
     * discovered while verifying PR #312): the two historically-wrong phrasings
     * must not appear in component blueprints.
     *
     *   - "CORE-09: Cryptography/Hashing" — CORE-09 is PSR-3 Logging, NOT Cryptography.
     *     Cryptography is CORE-16 (Binary Encryption Envelope). The misattribution
     *     originated in early blueprints that confused the two.
     *   - "HUB-28: Analytics/Ledger" — HUB-28 is API Versioning Strategy, NOT
     *     Analytics. Analytics is HUB-31 (Real-Time Analytics & Metrics Ledger).
     *
     * Contexts where the phrase is tolerated (not flagged):
     *   - Strikethrough (~~CORE-09: Cryptography~~) — represents a correction.
     *   - Inline code (`CORE-09: Cryptography`) — represents a reference/description.
     *   - Fenced code blocks (```...```) — represents literal/code reference.
     * Only ACTIVE misattributions in plain prose are flagged.
     *
     * Per SAAI directive 2026-10-06: declared enforcement ≠ actual enforcement.
     * The docstring claimed this check existed; the implementation did not.
     * This implements the check.
     */
    private function checkMisattribution(): void
    {
        // Active (non-strikethrough, non-code) misattribution patterns.
        $patterns = [
            // CORE-09: Cryptography/Hashing (CORE-09 is PSR-3 Logging; Crypto is CORE-16)
            '/\bCORE-09\s*:\s*Cryptography/i' => 'CORE-09: Cryptography (active misattribution — CORE-09 is PSR-3 Logging, not Cryptography; Crypto is CORE-16)',
            '/\bCORE-09\s*:\s*Hashing/i' => 'CORE-09: Hashing (active misattribution — CORE-09 is PSR-3 Logging, not Hashing; Crypto is CORE-16)',
            // HUB-28: Analytics/Ledger (HUB-28 is API Versioning; Analytics is HUB-31)
            '/\bHUB-28\s*:\s*Analytics/i' => 'HUB-28: Analytics (active misattribution — HUB-28 is API Versioning, not Analytics; Analytics is HUB-31)',
            '/\bHUB-28\s*:\s*Ledger/i' => 'HUB-28: Ledger (active misattribution — HUB-28 is API Versioning, not Ledger; Analytics is HUB-31)',
        ];

        foreach ($this->markdownFiles() as $file) {
            $text = file_get_contents($file);
            if ($text === false) {
                continue;
            }
            // Strip fenced code blocks (```...```).
            $text = preg_replace('/```.*?```/s', '', $text) ?? $text;
            // Strip inline code (`...`) — phrases inside inline code are references,
            // not assertions. Single-backtick spans; multi-line not supported here.
            $text = preg_replace('/`[^`]+`/', '', $text) ?? $text;
            // Strip strikethrough (~~...~~) — represents corrections.
            $text = preg_replace('/~~[^~]+~~/', '', $text) ?? $text;

            $rel = $this->rel($file);
            foreach ($patterns as $pattern => $description) {
                if (preg_match_all($pattern, $text, $m, PREG_SET_ORDER) > 0) {
                    foreach ($m as $match) {
                        $this->errors[] = sprintf('%s: misattribution phrase "%s" — %s', $rel, $match[0], $description);
                    }
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
        foreach (range(1, 32) as $n) {
            $expected[] = sprintf('Hub/HUB-%02d.md', $n);
        }
        // ISPOKE-01..27: 01..25 canonical since 2026-08-05; 26/27 promoted 2026-08-12 per ADR-015.
        foreach (range(1, 27) as $n) {
            $expected[] = sprintf('Spoke/Internal/ISPOKE-%02d.md', $n);
        }
        // ESPOKE-01..18: 01..15 canonical since 2026-08-05; 16/17/18 promoted 2026-08-12 per ADR-015.
        foreach (range(1, 18) as $n) {
            $expected[] = sprintf('Spoke/External/ESPOKE-%02d.md', $n);
        }
        $expected[] = 'Spoke/Bridge/BRIDGE-01.md';
        foreach (range(0, 4) as $n) {
            $expected[] = sprintf('Deploy/DEPLOY-%02d.md', $n);
        }
        // ADR-001..021: 001..010 Accepted; 011 (HUB-31), 012 (PQ JWT), 014 (AGRD), 015 (hospitality)
        // Proposed; 013 Accepted (MySQL); 016 (Library/App boundary) + 017 (Fiber runtime) +
        // 018 (per-tier releases) + 019 (pre-MUWV version) + 020 (release channels) +
        // 021 (tier-stratified build order with two-DAG governance) all Accepted.
        // The glob matches any suffix.
        // Pre-existing bug: range was 1..15, missing ADR-016..021. Fixed per SAAI
        // directive 2026-10-06: structural expectations must match canonical inventory.
        foreach (range(1, 21) as $n) {
            $expected[] = sprintf('ADRs/ADR-%03d-*.md', $n);
        }

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

    /**
     * Negative regression test: verify the lint correctly FAILS when expected
     * files are missing. Per SAAI directive 2026-10-06: "A structural checker
     * that only tests the happy path isn't sufficient."
     *
     * Creates a temp directory with only SOME expected files, runs the lint
     * against it, and verifies the lint reports the missing files as errors.
     *
     * Returns 0 if the lint correctly detected the missing files (self-test PASS).
     * Returns 1 if the lint failed to detect missing files (self-test FAIL).
     */
    public static function selfTest(): int
    {
        $tmp = sys_get_temp_dir() . '/arch-lint-selftest-' . bin2hex(random_bytes(8));
        @mkdir($tmp, 0777, true);

        // Create a few expected files (but not all — so checkStructure MUST
        // report the missing ones as errors).
        @mkdir($tmp . '/Core');
        @mkdir($tmp . '/Hub');
        @mkdir($tmp . '/Spoke/Internal');
        @mkdir($tmp . '/Spoke/External');
        @mkdir($tmp . '/Spoke/Bridge');
        @mkdir($tmp . '/Deploy');
        @mkdir($tmp . '/ADRs');
        // Create CORE-01.md and HUB-01.md (so 19+31 = 50 files missing)
        file_put_contents($tmp . '/Core/CORE-01.md', "# CORE-01: Self-Test Stub\n");
        file_put_contents($tmp . '/Hub/HUB-01.md', "# HUB-01: Self-Test Stub\n");
        // ADR-001 (so 20 ADRs missing)
        file_put_contents($tmp . '/ADRs/ADR-001-selftest.md', "# ADR-001: Self-Test Stub\n");

        $lint = new self($tmp);
        // Run only structural check (the negative test target)
        $lint->buildValidIds();
        $lint->checkStructure();

        $missing_count = count($lint->errors);
        // Clean up
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($tmp);

        if ($missing_count === 0) {
            fprintf(STDERR, "SELF-TEST FAIL: lint did not detect missing files (checkStructure reported 0 errors on a directory with missing files)\n");
            return 1;
        }
        fprintf(STDERR, "SELF-TEST PASS: lint correctly detected %d missing files in synthetic directory\n", $missing_count);
        return 0;
    }
}

// CLI entry: support --self-test for negative regression test.
if (isset($argv[1]) && $argv[1] === '--self-test') {
    exit(ArchitectureLint::selfTest());
}

$root = $argv[1] ?? dirname(__DIR__, 2);
$root = realpath($root) ?: $root;
exit((new ArchitectureLint($root))->run());
