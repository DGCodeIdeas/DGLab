<?php
/**
 * M0 — Architecture Baseline Generator
 *
 * Per SPEC-001 §39 (Phase 0 — Baseline and Protection):
 *   "Create a machine-readable architecture baseline containing at minimum:
 *    repository_commit, php_version, core_packages, hub_packages,
 *    internal_spokes, external_spokes, bridge_packages, deploy_packages,
 *    architecture_decisions, test_suites"
 *
 * Walks the live DGLab repository and emits a reproducible Markdown
 * baseline. The baseline is the foundation for M0 (Protected Baseline)
 * exit criteria: "Baseline generation is reproducible from a clean checkout."
 *
 * Usage:
 *   php /home/z/my-project/scripts/generate-architecture-baseline.php
 *
 * Output:
 *   /home/z/my-project/download/ARCHITECTURE_BASELINE.md
 *
 * @package SovereignStack\Verification
 */

declare(strict_types=1);

final class ArchitectureBaselineGenerator
{
    private string $repoRoot;
    private string $outputPath;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(string $repoRoot, string $outputPath)
    {
        $this->repoRoot = rtrim($repoRoot, '/');
        $this->outputPath = $outputPath;
    }

    public function run(): int
    {
        $this->collectRepositoryState();
        $this->collectPhpVersion();
        $this->collectPackageCounts();
        $this->collectBlueprintCounts();
        $this->collectAdrCount();
        $this->collectTestSuites();
        $this->collectFrozenContracts();
        $this->collectWorkerRecyclingValues();
        $this->emitMarkdown();

        fprintf(STDERR, "architecture-baseline: wrote %s\n", $this->outputPath);
        return 0;
    }

    private function collectRepositoryState(): void
    {
        $head = $this->git('rev-parse HEAD');
        $short = $this->git('rev-parse --short HEAD');
        $branch = $this->git('rev-parse --abbrev-ref HEAD');
        $describe = $this->git('describe --tags --always 2>/dev/null');

        $this->data['repository'] = [
            'commit' => trim($head),
            'short_commit' => trim($short),
            'branch' => trim($branch),
            'describe' => trim($describe),
            'generated_at' => date('c'),
            'generated_at_epoch' => time(),
        ];
    }

    private function collectPhpVersion(): void
    {
        $composerJsonPath = $this->repoRoot . '/composer.json';
        if (!is_file($composerJsonPath)) {
            fprintf(STDERR, "warning: composer.json not found at %s\n", $composerJsonPath);
            return;
        }
        $decoded = json_decode(file_get_contents($composerJsonPath) ?: '', true);
        if (!is_array($decoded)) {
            fprintf(STDERR, "warning: composer.json invalid JSON\n");
            return;
        }
        $this->data['php_version'] = [
            'constraint' => $decoded['require']['php'] ?? 'unspecified',
            'runtime' => PHP_VERSION,
        ];

        // Also capture dev dependencies relevant to baseline
        $this->data['tooling'] = [
            'phpunit' => $decoded['require-dev']['phpunit/phpunit'] ?? 'not in root composer.json',
            'phpstan' => $decoded['require-dev']['phpstan/phpstan'] ?? 'not in root composer.json',
        ];
    }

    private function collectPackageCounts(): void
    {
        // Discover packages by finding composer.json files in packages/*/*
        $tiers = [
            'core' => 'packages/core',
            'hub' => 'packages/hub',
            'spoke_internal' => 'packages/spoke/internal',
            'spoke_external' => 'packages/spoke/external',
            'bridge' => 'packages/bridge',
        ];

        $this->data['packages'] = [];
        $totalPackages = 0;
        $totalPackagesWithTests = 0;

        foreach ($tiers as $tierKey => $tierDir) {
            $fullTierDir = $this->repoRoot . '/' . $tierDir;
            $packages = [];
            if (is_dir($fullTierDir)) {
                foreach (scandir($fullTierDir) ?: [] as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $pkgComposerJson = $fullTierDir . '/' . $entry . '/composer.json';
                    $pkgSrcDir = $fullTierDir . '/' . $entry . '/src';
                    $pkgTestsDir = $fullTierDir . '/' . $entry . '/tests';

                    if (!is_dir($pkgSrcDir) && !is_file($pkgComposerJson)) {
                        continue;
                    }

                    $pkgInfo = [
                        'name' => $entry,
                        'path' => $tierDir . '/' . $entry,
                        'has_composer_json' => is_file($pkgComposerJson),
                        'has_src' => is_dir($pkgSrcDir),
                        'has_tests' => is_dir($pkgTestsDir),
                        'src_file_count' => 0,
                        'test_file_count' => 0,
                    ];

                    if ($pkgInfo['has_src']) {
                        $pkgInfo['src_file_count'] = $this->countPhpFiles($pkgSrcDir);
                    }
                    if ($pkgInfo['has_tests']) {
                        $pkgInfo['test_file_count'] = $this->countPhpFiles($pkgTestsDir);
                        $totalPackagesWithTests++;
                    }

                    $packages[] = $pkgInfo;
                    $totalPackages++;
                }
            }
            $this->data['packages'][$tierKey] = [
                'path' => $tierDir,
                'count' => count($packages),
                'packages' => $packages,
            ];
        }

        $this->data['packages']['totals'] = [
            'total_packages' => $totalPackages,
            'total_with_tests' => $totalPackagesWithTests,
        ];
    }

    private function collectBlueprintCounts(): void
    {
        $blueprintRoot = $this->repoRoot . '/archive/Arc/Blueprints';
        $tiers = [
            'Core' => $blueprintRoot . '/Core',
            'Hub' => $blueprintRoot . '/Hub',
            'Spoke_Internal' => $blueprintRoot . '/Spoke/Internal',
            'Spoke_External' => $blueprintRoot . '/Spoke/External',
            'Spoke_Bridge' => $blueprintRoot . '/Spoke/Bridge',
            'Deploy' => $blueprintRoot . '/Deploy',
        ];

        $this->data['blueprints'] = [];
        $totalBlueprints = 0;
        foreach ($tiers as $tierKey => $tierDir) {
            $count = 0;
            $files = [];
            if (is_dir($tierDir)) {
                foreach (scandir($tierDir) ?: [] as $entry) {
                    if (str_ends_with($entry, '.md')) {
                        $count++;
                        $files[] = $entry;
                    }
                }
            }
            $this->data['blueprints'][$tierKey] = [
                'path' => str_replace($this->repoRoot . '/', '', $tierDir),
                'count' => $count,
                'files' => $files,
            ];
            $totalBlueprints += $count;
        }
        $this->data['blueprints']['total'] = $totalBlueprints;
    }

    private function collectAdrCount(): void
    {
        $adrDir = $this->repoRoot . '/Architecture/ADRs';
        $adrs = [];
        if (is_dir($adrDir)) {
            foreach (scandir($adrDir) ?: [] as $entry) {
                if (preg_match('/^ADR-\d+.*\.md$/', $entry)) {
                    $adrs[] = $entry;
                }
            }
        }
        sort($adrs);
        $this->data['adrs'] = [
            'path' => 'Architecture/ADRs',
            'count' => count($adrs),
            'files' => $adrs,
        ];
    }

    private function collectTestSuites(): void
    {
        $testSuites = [];
        $packages = $this->data['packages'] ?? [];
        foreach ($packages as $tierKey => $tierData) {
            if (!isset($tierData['packages']) || !is_array($tierData['packages'])) {
                continue;
            }
            foreach ($tierData['packages'] as $pkg) {
                if ($pkg['has_tests'] ?? false) {
                    $testSuites[] = $pkg['path'];
                }
            }
        }
        $this->data['test_suites'] = [
            'count' => count($testSuites),
            'paths' => $testSuites,
        ];
    }

    private function collectFrozenContracts(): void
    {
        $frozenContractsPath = $this->repoRoot . '/Architecture/FROZEN-CONTRACTS.md';
        if (!is_file($frozenContractsPath)) {
            return;
        }
        $content = file_get_contents($frozenContractsPath) ?: '';
        // Count rows that look like "| CORE-XX |" or "| HUB-XX |" etc.
        $count = 0;
        $perTier = [];
        foreach (['CORE', 'HUB', 'ISPOKE', 'ESPOKE', 'BRIDGE', 'DEPLOY'] as $prefix) {
            $matches = 0;
            if (preg_match_all('/^\| ' . $prefix . '-\d+\b/im', $content, $allMatches)) {
                $matches = count($allMatches[0]);
            }
            $perTier[$prefix] = $matches;
            $count += $matches;
        }
        $this->data['frozen_contracts'] = [
            'path' => 'Architecture/FROZEN-CONTRACTS.md',
            'total' => $count,
            'per_tier' => $perTier,
        ];
    }

    private function collectWorkerRecyclingValues(): void
    {
        $caddyfileBlue = $this->repoRoot . '/anvil/app/Caddyfile.blue';
        $systemdUnit = $this->repoRoot . '/anvil/systemd/anvil-frankenphp@.service';

        $values = [];
        if (is_file($caddyfileBlue)) {
            $content = file_get_contents($caddyfileBlue) ?: '';
            if (preg_match('/max_requests\s+(\d+)/', $content, $m)) {
                $values['max_requests'] = $m[1];
            }
            if (preg_match('/"memory_limit"\s+"([^"]+)"/', $content, $m)) {
                $values['memory_limit'] = $m[1];
            }
        }
        if (is_file($systemdUnit)) {
            $content = file_get_contents($systemdUnit) ?: '';
            foreach (['Restart', 'RestartSec', 'TimeoutStopSec', 'KillSignal'] as $key) {
                if (preg_match('/^' . $key . '=(\S+)/m', $content, $m)) {
                    $values[$key] = $m[1];
                }
            }
        }
        $this->data['worker_recycling'] = $values;
    }

    private function countPhpFiles(string $dir): int
    {
        $count = 0;
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $count++;
            }
        }
        return $count;
    }

    private function git(string $args): string
    {
        $cmd = sprintf('cd %s && git %s 2>&1', escapeshellarg($this->repoRoot), $args);
        $output = shell_exec($cmd);
        return is_string($output) ? $output : '';
    }

    private function emitMarkdown(): void
    {
        $md = $this->renderMarkdown();
        $dir = dirname($this->outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->outputPath, $md);
    }

    private function renderMarkdown(): string
    {
        $repo = $this->data['repository'];
        $php = $this->data['php_version'];
        $tooling = $this->data['tooling'] ?? [];
        $packages = $this->data['packages'];
        $blueprints = $this->data['blueprints'];
        $adrs = $this->data['adrs'];
        $testSuites = $this->data['test_suites'];
        $frozen = $this->data['frozen_contracts'] ?? [];
        $worker = $this->data['worker_recycling'] ?? [];

        $out = [];
        $out[] = '# ARCHITECTURE BASELINE — M0 (Protected Baseline)';
        $out[] = '';
        $out[] = '**Purpose:** Machine-generated, reproducible baseline of the DGLab repository per SPEC-001 §39.';
        $out[] = '';
        $out[] = '**Generated:** ' . $repo['generated_at'];
        $out[] = '';
        $out[] = '---';
        $out[] = '';
        $out[] = '## Repository State';
        $out[] = '';
        $out[] = '| Field | Value |';
        $out[] = '|---|---|';
        $out[] = '| Commit (full) | `' . $repo['commit'] . '` |';
        $out[] = '| Commit (short) | `' . $repo['short_commit'] . '` |';
        $out[] = '| Branch | `' . $repo['branch'] . '` |';
        $out[] = '| Git describe | `' . $repo['describe'] . '` |';
        $out[] = '| Generated at | ' . $repo['generated_at'] . ' (' . $repo['generated_at_epoch'] . ') |';
        $out[] = '';
        $out[] = '## PHP Runtime & Tooling';
        $out[] = '';
        $out[] = '| Field | Value |';
        $out[] = '|---|---|';
        $out[] = '| PHP constraint (composer.json) | `' . $php['constraint'] . '` |';
        $out[] = '| PHP runtime (generator) | `' . $php['runtime'] . '` |';
        $out[] = '| PHPUnit (root) | `' . ($tooling['phpunit'] ?? 'n/a') . '` |';
        $out[] = '| PHPStan (root) | `' . ($tooling['phpstan'] ?? 'n/a') . '` |';
        $out[] = '';
        $out[] = '## Package Distribution (per tier)';
        $out[] = '';
        $out[] = '| Tier | Path | Package count |';
        $out[] = '|---|---|---|';
        foreach (['core', 'hub', 'spoke_internal', 'spoke_external', 'bridge'] as $tierKey) {
            $tier = $packages[$tierKey] ?? ['path' => '', 'count' => 0];
            $out[] = sprintf('| %s | `%s` | %d |', $tierKey, $tier['path'], $tier['count']);
        }
        $out[] = sprintf('| **TOTAL** | | **%d** |', $packages['totals']['total_packages'] ?? 0);
        $out[] = '';
        $out[] = sprintf('Packages with `tests/` directory: **%d / %d**', $packages['totals']['total_with_tests'] ?? 0, $packages['totals']['total_packages'] ?? 0);
        $out[] = '';

        // Detailed package listing
        $out[] = '<details><summary>Package-by-package detail (click to expand)</summary>';
        $out[] = '';
        foreach (['core', 'hub', 'spoke_internal', 'spoke_external', 'bridge'] as $tierKey) {
            $tier = $packages[$tierKey] ?? ['packages' => []];
            $out[] = '### ' . $tierKey;
            $out[] = '';
            $out[] = '| Package | Has composer.json | Has src/ | Has tests/ | src files | test files |';
            $out[] = '|---|---|---|---|---|---|';
            foreach ($tier['packages'] ?? [] as $pkg) {
                $out[] = sprintf(
                    '| `%s` | %s | %s | %s | %d | %d |',
                    $pkg['path'],
                    $pkg['has_composer_json'] ? '✅' : '❌',
                    $pkg['has_src'] ? '✅' : '❌',
                    $pkg['has_tests'] ? '✅' : '❌',
                    $pkg['src_file_count'],
                    $pkg['test_file_count']
                );
            }
            $out[] = '';
        }
        $out[] = '</details>';
        $out[] = '';

        $out[] = '## Blueprint Counts (per tier)';
        $out[] = '';
        $out[] = '| Tier | Path | Count |';
        $out[] = '|---|---|---|';
        foreach (['Core', 'Hub', 'Spoke_Internal', 'Spoke_External', 'Spoke_Bridge', 'Deploy'] as $tierKey) {
            $tier = $blueprints[$tierKey] ?? ['path' => '', 'count' => 0];
            $out[] = sprintf('| %s | `%s` | %d |', $tierKey, $tier['path'], $tier['count']);
        }
        $out[] = sprintf('| **TOTAL** | | **%d** |', $blueprints['total'] ?? 0);
        $out[] = '';

        $out[] = '## Architecture Decision Records';
        $out[] = '';
        $out[] = sprintf('Count: **%d** at `%s`', $adrs['count'], $adrs['path']);
        $out[] = '';
        $out[] = 'Files:';
        $out[] = '';
        $out[] = '<details><summary>ADR file list (click to expand)</summary>';
        $out[] = '';
        $out[] = '```';
        foreach ($adrs['files'] as $file) {
            $out[] = $file;
        }
        $out[] = '```';
        $out[] = '';
        $out[] = '</details>';
        $out[] = '';

        $out[] = '## Test Suites';
        $out[] = '';
        $out[] = sprintf('Packages with `tests/` directory: **%d**', $testSuites['count']);
        $out[] = '';
        $out[] = '<details><summary>Test suite paths (click to expand)</summary>';
        $out[] = '';
        $out[] = '```';
        foreach ($testSuites['paths'] as $path) {
            $out[] = $path;
        }
        $out[] = '```';
        $out[] = '';
        $out[] = '</details>';
        $out[] = '';

        if (!empty($frozen)) {
            $out[] = '## Frozen Contracts';
            $out[] = '';
            $out[] = sprintf('Source: `%s`', $frozen['path']);
            $out[] = '';
            $out[] = '| Tier | Count |';
            $out[] = '|---|---|';
            foreach ($frozen['per_tier'] as $tier => $count) {
                $out[] = sprintf('| %s | %d |', $tier, $count);
            }
            $out[] = sprintf('| **TOTAL** | **%d** |', $frozen['total']);
            $out[] = '';
        }

        if (!empty($worker)) {
            $out[] = '## Worker Recycling Configuration (Production)';
            $out[] = '';
            $out[] = 'Source: `anvil/app/Caddyfile.blue` + `anvil/systemd/anvil-frankenphp@.service`';
            $out[] = '';
            $out[] = '| Parameter | Value |';
            $out[] = '|---|---|';
            foreach ($worker as $key => $value) {
                $out[] = sprintf('| `%s` | `%s` |', $key, $value);
            }
            $out[] = '';
        }

        $out[] = '## Reproducibility';
        $out[] = '';
        $out[] = 'This baseline is reproducible from a clean checkout by running:';
        $out[] = '```';
        $out[] = 'php /home/z/my-project/scripts/generate-architecture-baseline.php';
        $out[] = '```';
        $out[] = '';
        $out[] = 'The generator walks repository state directly — no manual data entry. Per SPEC-001 §37 governance principle: *“Executable repository state is authoritative wherever implementation status can be determined automatically.”*';
        $out[] = '';
        $out[] = '---';
        $out[] = '';
        $out[] = '*Generated by `scripts/generate-architecture-baseline.php` per SPEC-001 §39 Phase 0.*';
        $out[] = '';

        return implode("\n", $out);
    }
}

// --- Entry point ---
$repoRoot = '/home/z/my-project';
$outputPath = '/home/z/my-project/download/ARCHITECTURE_BASELINE.md';
$generator = new ArchitectureBaselineGenerator($repoRoot, $outputPath);
exit($generator->run());
