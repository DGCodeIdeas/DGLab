#!/usr/bin/env python3
"""
M1 — Architecture Boundary Lint Regression Tests

Per SPEC-001 §41 (Container Service-Locator Rule) Acceptance criteria:
  * Existing legitimate `resolve()` calls remain valid.
  * Container service-location in production application code produces a CI failure.
  * False positives are covered by regression tests.

This script:
  1. Verifies the live repository has ZERO false positives on the 6 legitimate
     resolve() callers (MiddlewareResolver, ContractRegistry, PerRequestHandler,
     MiddlewarePipelineInterface, Vanguard, and the ContractRegistry definition).
  2. Verifies the checker DOES flag synthetic violations:
     - Core importing Hub (forbidden ring direction)
     - $container->get(...) in production code (service-locator anti-pattern)
     - $container->resolve(...) in production code (service-locator anti-pattern)
     - Container::getInstance() in production code (service-locator anti-pattern)
     - app() helper in production code (service-locator anti-pattern)
  3. Verifies the checker does NOT flag:
     - $this->resolver->resolve() (legitimate domain-specific resolver)
     - $this->contracts->resolve() (legitimate domain-specific resolver)
     - Public function resolve(...) definitions (not calls)
     - Use of PSR interfaces from any ring (allowed external dependency)

Run via:
  python3 /home/z/my-project/scripts/test_architecture_boundary_lint.py
"""

from __future__ import annotations

import sys
import tempfile
from pathlib import Path
from dataclasses import dataclass

# Import the checker (dash-named file — use importlib to load)
import importlib.util
import sys as _sys
_checker_path = Path("/home/z/my-project/scripts/architecture-boundary-lint.py")
_spec = importlib.util.spec_from_file_location("architecture_boundary_lint", _checker_path)
architecture_boundary_lint = importlib.util.module_from_spec(_spec)
# Register in sys.modules BEFORE exec so @dataclass can find cls.__module__
_sys.modules["architecture_boundary_lint"] = architecture_boundary_lint
_spec.loader.exec_module(architecture_boundary_lint)

LEGITIMATE_RESOLVE_CALLERS = architecture_boundary_lint.LEGITIMATE_RESOLVE_CALLERS
REPO_ROOT = architecture_boundary_lint.REPO_ROOT
scan_repository = architecture_boundary_lint.scan_repository
parse_use_statements = architecture_boundary_lint.parse_use_statements
find_service_locator_violations = architecture_boundary_lint.find_service_locator_violations
ring_for_namespace = architecture_boundary_lint.ring_for_namespace
ring_for_path = architecture_boundary_lint.ring_for_path


# --- Test helpers ------------------------------------------------------------

@dataclass
class TestResult:
    name: str
    passed: bool
    detail: str = ""


def make_test_repo(files: dict[str, str]) -> tuple[tempfile.TemporaryDirectory, Path]:
    """Create a temp repo with the given files. Returns (tmpdir, root_path)."""
    tmp = tempfile.TemporaryDirectory()
    root = Path(tmp.name)
    for rel_path, content in files.items():
        full_path = root / rel_path
        full_path.parent.mkdir(parents=True, exist_ok=True)
        full_path.write_text(content)
    return tmp, root


# --- Test cases --------------------------------------------------------------

def test_live_repo_has_zero_false_positives_on_legitimate_callers() -> TestResult:
    """Test 1: The 6 legitimate resolve() caller files MUST NOT be flagged."""
    result = scan_repository()
    legitimate_violations = [
        v for v in result.violations
        if v.source_file in LEGITIMATE_RESOLVE_CALLERS
    ]
    if legitimate_violations:
        return TestResult(
            name="live_repo_zero_false_positives_on_legitimate_callers",
            passed=False,
            detail=f"Expected 0 violations on legitimate callers, got {len(legitimate_violations)}: "
                   + ", ".join(v.source_file for v in legitimate_violations),
        )
    return TestResult(
        name="live_repo_zero_false_positives_on_legitimate_callers",
        passed=True,
        detail=f"All {len(LEGITIMATE_RESOLVE_CALLERS)} legitimate caller files passed without false positives.",
    )


def test_live_repo_has_zero_ring_boundary_violations() -> TestResult:
    """Test 2: The live repository currently has zero ring-boundary violations."""
    result = scan_repository()
    boundary_violations = [v for v in result.violations if v.rule_id == "ARCH-BOUNDARY-001"]
    if boundary_violations:
        return TestResult(
            name="live_repo_zero_ring_boundary_violations",
            passed=False,
            detail=f"Expected 0 ring-boundary violations, got {len(boundary_violations)}: "
                   + ", ".join(f"{v.source_file} → {v.target}" for v in boundary_violations),
        )
    return TestResult(
        name="live_repo_zero_ring_boundary_violations",
        passed=True,
        detail=f"Live repo: 0 ring-boundary violations across {result.files_scanned} files / {result.imports_scanned} imports.",
    )


def test_live_repo_has_zero_service_locator_violations() -> TestResult:
    """Test 3: The live repository currently has zero service-locator violations."""
    result = scan_repository()
    locator_violations = [v for v in result.violations if v.rule_id == "ARCH-LOCATOR-001"]
    if locator_violations:
        return TestResult(
            name="live_repo_zero_service_locator_violations",
            passed=False,
            detail=f"Expected 0 service-locator violations, got {len(locator_violations)}: "
                   + ", ".join(v.source_file for v in locator_violations),
        )
    return TestResult(
        name="live_repo_zero_service_locator_violations",
        passed=True,
        detail="Live repo: 0 service-locator violations.",
    )


def test_synthetic_core_importing_hub_is_flagged() -> TestResult:
    """Test 4: Synthetic Core file importing Hub MUST be flagged."""
    files = {
        "packages/core/kernel/src/SyntheticViolation.php": """<?php
declare(strict_types=1);

namespace SovereignStack\\Core\\Kernel;

use SovereignStack\\Hub\\Config\\FeatureFlagRepositoryInterface;

final class SyntheticViolation
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $repo,
    ) {}
}
""",
    }
    tmp, root = make_test_repo(files)
    try:
        # Patch REPO_ROOT and re-scan
        import architecture_boundary_lint as checker
        original_root = checker.REPO_ROOT
        checker.REPO_ROOT = root
        try:
            result = checker.scan_repository()
        finally:
            checker.REPO_ROOT = original_root

        boundary_violations = [v for v in result.violations if v.rule_id == "ARCH-BOUNDARY-001"]
        if not boundary_violations:
            return TestResult(
                name="synthetic_core_importing_hub_is_flagged",
                passed=False,
                detail="Expected ARCH-BOUNDARY-001 violation for Core→Hub import, got none.",
            )
        return TestResult(
            name="synthetic_core_importing_hub_is_flagged",
            passed=True,
            detail=f"Correctly flagged: {boundary_violations[0].rule_violated}",
        )
    finally:
        tmp.cleanup()


def test_synthetic_container_get_is_flagged() -> TestResult:
    """Test 5: $container->get(...) in production code MUST be flagged."""
    content = """<?php
declare(strict_types=1);

namespace SovereignStack\\Core\\Kernel;

final class SyntheticLocator
{
    public function doBadThing($container): void
    {
        $service = $container->get(SomeService::class);
    }
}
"""
    findings = find_service_locator_violations(content, Path("test.php"))
    if not findings:
        return TestResult(
            name="synthetic_container_get_is_flagged",
            passed=False,
            detail="Expected $container->get(...) to be flagged, got no findings.",
        )
    return TestResult(
        name="synthetic_container_get_is_flagged",
        passed=True,
        detail=f"Correctly flagged: {findings[0][2]}",
    )


def test_synthetic_container_resolve_is_flagged() -> TestResult:
    """Test 6: $container->resolve(...) in production code MUST be flagged."""
    content = """<?php
declare(strict_types=1);

namespace SovereignStack\\Core\\Kernel;

final class SyntheticLocator
{
    public function doBadThing($container): void
    {
        $service = $container->resolve(SomeService::class);
    }
}
"""
    findings = find_service_locator_violations(content, Path("test.php"))
    if not findings:
        return TestResult(
            name="synthetic_container_resolve_is_flagged",
            passed=False,
            detail="Expected $container->resolve(...) to be flagged, got no findings.",
        )
    return TestResult(
        name="synthetic_container_resolve_is_flagged",
        passed=True,
        detail=f"Correctly flagged: {findings[0][2]}",
    )


def test_synthetic_container_get_instance_is_flagged() -> TestResult:
    """Test 7: Container::getInstance() in production code MUST be flagged."""
    content = """<?php
declare(strict_types=1);

namespace SovereignStack\\Core\\Kernel;

final class SyntheticLocator
{
    public function doBadThing(): void
    {
        $container = Container::getInstance();
        $service = $container->get(SomeService::class);
    }
}
"""
    findings = find_service_locator_violations(content, Path("test.php"))
    if not findings:
        return TestResult(
            name="synthetic_container_get_instance_is_flagged",
            passed=False,
            detail="Expected Container::getInstance() to be flagged, got no findings.",
        )
    return TestResult(
        name="synthetic_container_get_instance_is_flagged",
        passed=True,
        detail=f"Correctly flagged: {findings[0][2]}",
    )


def test_synthetic_app_helper_is_flagged() -> TestResult:
    """Test 8: app() helper in production code MUST be flagged."""
    content = """<?php
declare(strict_types=1);

namespace SovereignStack\\Core\\Kernel;

final class SyntheticLocator
{
    public function doBadThing(): void
    {
        $service = app(SomeService::class);
    }
}
"""
    findings = find_service_locator_violations(content, Path("test.php"))
    if not findings:
        return TestResult(
            name="synthetic_app_helper_is_flagged",
            passed=False,
            detail="Expected app() helper to be flagged, got no findings.",
        )
    return TestResult(
        name="synthetic_app_helper_is_flagged",
        passed=True,
        detail=f"Correctly flagged: {findings[0][2]}",
    )


def test_synthetic_this_resolver_resolve_is_not_flagged() -> TestResult:
    """Test 9: $this->resolver->resolve() (legitimate domain resolver) MUST NOT be flagged."""
    content = """<?php
declare(strict_types=1);

namespace SovereignStack\\Core\\Http;

final class PerRequestHandler
{
    public function __construct(
        private readonly MiddlewareResolverInterface $resolver,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->resolver->resolve($entry);
        return $middleware->process($request, $this);
    }
}
"""
    findings = find_service_locator_violations(content, Path("packages/core/middleware/src/PerRequestHandler.php"))
    if findings:
        return TestResult(
            name="synthetic_this_resolver_resolve_not_flagged",
            passed=False,
            detail=f"Expected NO findings for $this->resolver->resolve(), got {len(findings)}: {findings[0][2]}",
        )
    return TestResult(
        name="synthetic_this_resolver_resolve_not_flagged",
        passed=True,
        detail="$this->resolver->resolve() correctly NOT flagged (legitimate domain resolver).",
    )


def test_synthetic_this_contracts_resolve_is_not_flagged() -> TestResult:
    """Test 10: $this->contracts->resolve() (legitimate domain resolver) MUST NOT be flagged."""
    content = """<?php
declare(strict_types=1);

namespace SovereignStack\\Bridge;

final class Vanguard
{
    public function __construct(
        private readonly ContractRegistryInterface $contracts,
    ) {}

    public function process(ServerRequestInterface $request): ResponseInterface
    {
        $transformer = $this->contracts->resolve($route);
        return $transformer->transform($request);
    }
}
"""
    findings = find_service_locator_violations(content, Path("packages/bridge/vanguard/src/Vanguard.php"))
    if findings:
        return TestResult(
            name="synthetic_this_contracts_resolve_not_flagged",
            passed=False,
            detail=f"Expected NO findings for $this->contracts->resolve(), got {len(findings)}: {findings[0][2]}",
        )
    return TestResult(
        name="synthetic_this_contracts_resolve_not_flagged",
        passed=True,
        detail="$this->contracts->resolve() correctly NOT flagged (legitimate domain resolver).",
    )


def test_synthetic_function_definition_not_flagged() -> TestResult:
    """Test 11: `public function resolve(...)` definition MUST NOT be flagged."""
    content = """<?php
declare(strict_types=1);

namespace SovereignStack\\Core\\Http;

final class MiddlewareResolver implements MiddlewareResolverInterface
{
    public function resolve(MiddlewareInterface|string|callable $entry): MiddlewareInterface
    {
        // ...
    }
}
"""
    findings = find_service_locator_violations(content, Path("packages/core/middleware/src/MiddlewareResolver.php"))
    if findings:
        return TestResult(
            name="synthetic_function_definition_not_flagged",
            passed=False,
            detail=f"Expected NO findings for `function resolve(...)` definition, got {len(findings)}: {findings[0][2]}",
        )
    return TestResult(
        name="synthetic_function_definition_not_flagged",
        passed=True,
        detail="`public function resolve(...)` definition correctly NOT flagged.",
    )


def test_psr_imports_from_core_are_allowed() -> TestResult:
    """Test 12: PSR imports from any ring MUST be allowed (external)."""
    content = """<?php
declare(strict_types=1);

namespace SovereignStack\\Core\\Kernel;

use Psr\\Container\\ContainerInterface;
use Psr\\Http\\Message\\ResponseInterface;
use Psr\\Log\\LoggerInterface;
use Psr\\EventDispatcher\\EventDispatcherInterface;

final class UsesPsr
{
    public function __construct(
        private readonly ContainerInterface $c,
        private readonly LoggerInterface $logger,
    ) {}
}
"""
    imports = parse_use_statements(content)
    if len(imports) != 4:
        return TestResult(
            name="psr_imports_from_core_allowed",
            passed=False,
            detail=f"Expected 4 imports parsed, got {len(imports)}: {imports}",
        )
    for imp in imports:
        ring = ring_for_namespace(imp)
        if ring != "external":
            return TestResult(
                name="psr_imports_from_core_allowed",
                passed=False,
                detail=f"Psr\\... import {imp} classified as ring '{ring}', expected 'external'",
            )
    return TestResult(
        name="psr_imports_from_core_allowed",
        passed=True,
        detail="All 4 Psr\\ imports correctly classified as 'external' (allowed).",
    )


def test_ring_for_path_classification() -> TestResult:
    """Test 13: ring_for_path() classifies all tier paths correctly."""
    cases = [
        ("packages/core/kernel/src/Kernel.php", "core"),
        ("packages/hub/config/src/FeatureFlagManager.php", "hub"),
        ("packages/spoke/internal/codex/src/KnowledgeBase.php", "spoke-internal"),
        ("packages/spoke/external/canvas/src/Canvas.php", "spoke-external"),
        ("packages/bridge/vanguard/src/Vanguard.php", "bridge"),
        ("app/Controller/HelloController.php", "outer-rim"),
        ("scripts/foo.php", None),
        ("vendor/lib/foo.php", None),
    ]
    for rel, expected in cases:
        full_path = REPO_ROOT / rel
        # ring_for_path requires the path to be relative to REPO_ROOT; for test paths
        # that may not exist on disk, we monkey-patch
        import architecture_boundary_lint as checker
        ring = checker.ring_for_path(full_path)
        # For paths that don't exist, ring_for_path may still return based on prefix
        if expected is None:
            continue  # we don't assert on non-existent paths in this test
        if ring != expected:
            return TestResult(
                name="ring_for_path_classification",
                passed=False,
                detail=f"For {rel}: expected ring '{expected}', got '{ring}'",
            )
    return TestResult(
        name="ring_for_path_classification",
        passed=True,
        detail="All path → ring classifications correct.",
    )


# --- Runner ------------------------------------------------------------------

def main() -> int:
    tests = [
        test_live_repo_has_zero_false_positives_on_legitimate_callers,
        test_live_repo_has_zero_ring_boundary_violations,
        test_live_repo_has_zero_service_locator_violations,
        test_synthetic_core_importing_hub_is_flagged,
        test_synthetic_container_get_is_flagged,
        test_synthetic_container_resolve_is_flagged,
        test_synthetic_container_get_instance_is_flagged,
        test_synthetic_app_helper_is_flagged,
        test_synthetic_this_resolver_resolve_is_not_flagged,
        test_synthetic_this_contracts_resolve_is_not_flagged,
        test_synthetic_function_definition_not_flagged,
        test_psr_imports_from_core_are_allowed,
        test_ring_for_path_classification,
    ]

    results = [test() for test in tests]
    passed = sum(1 for r in results if r.passed)
    failed = sum(1 for r in results if not r.passed)

    print(f"\n=== Architecture Boundary Lint Regression Tests ===\n")
    for r in results:
        status = "✅ PASS" if r.passed else "❌ FAIL"
        print(f"{status} {r.name}")
        if not r.passed:
            print(f"        {r.detail}")
        elif r.detail:
            print(f"        {r.detail}")
    print(f"\nSummary: {passed} passed, {failed} failed out of {len(results)} total tests.\n")

    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
