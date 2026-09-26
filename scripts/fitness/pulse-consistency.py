#!/usr/bin/env python3
"""FF-01: Pulse 6-Tuple Consistency — verifies RequestContext field consistency."""
import json, re, sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
CANONICAL_PULSE_FIELDS = ("requestId", "traceId", "tenantId", "startedAt", "routeName", "userId")

def check_request_context():
    """Parse RequestContext.php and verify constructor fields match canonical tuple."""
    rc_path = REPO_ROOT / "packages/core/kernel/src/RequestContext.php"
    violations = []
    
    if not rc_path.exists():
        violations.append({"rule": "PULSE-001", "file": str(rc_path), "message": "RequestContext.php not found"})
        return violations
    
    content = rc_path.read_text()
    
    # Extract constructor parameters from the PHP readonly class
    # Look for: public string $requestId, public ?string $tenantId, etc.
    param_re = re.compile(r'public\s+(?:readonly\s+)?(?:\?[\w\\]+|\w+)\s+\$(\w+)')
    
    # Find the constructor block
    ctor_match = re.search(r'public function __construct\((.*?)\)', content, re.DOTALL)
    if not ctor_match:
        violations.append({"rule": "PULSE-001", "file": str(rc_path), "message": "Constructor not found"})
        return violations
    
    found_fields = param_re.findall(ctor_match.group(1))
    
    if found_fields != list(CANONICAL_PULSE_FIELDS):
        violations.append({
            "rule": "PULSE-001",
            "file": str(rc_path),
            "line": content[:ctor_match.start()].count('\n') + 1,
            "expected": ", ".join(CANONICAL_PULSE_FIELDS),
            "found": ", ".join(found_fields),
            "message": f"RequestContext constructor fields don't match canonical Pulse tuple"
        })
    
    # Check documentation references to the tuple
    doc_re = re.compile(r'(?:requestId|traceId|tenantId|startedAt|routeName|userId)', re.IGNORECASE)
    # Check that the docblock lists all 6 fields
    docblock_match = re.search(r'/\*\*.*?\*/', content[:ctor_match.start()], re.DOTALL)
    if docblock_match:
        doc_fields = set(doc_re.findall(docblock_match.group(0)))
        canonical_set = set(CANONICAL_PULSE_FIELDS)
        missing_in_doc = canonical_set - doc_fields
        if missing_in_doc:
            violations.append({
                "rule": "PULSE-001",
                "file": str(rc_path),
                "message": f"Docblock missing Pulse fields: {', '.join(sorted(missing_in_doc))}"
            })
    
    return violations

def main():
    violations = check_request_context()
    
    result = {
        "check": "FF-01 Pulse Consistency",
        "canonical_tuple": list(CANONICAL_PULSE_FIELDS),
        "violations": violations,
        "violation_count": len(violations),
    }
    print(json.dumps(result, indent=2))
    
    if violations:
        print("\n=== FF-01 Pulse Consistency Report ===", file=sys.stderr)
        for v in violations:
            print(f"[{v['rule']}] {v['file']}", file=sys.stderr)
            if 'expected' in v:
                print(f"  expected: {v['expected']}", file=sys.stderr)
                print(f"  found:    {v['found']}", file=sys.stderr)
            print(f"  {v['message']}", file=sys.stderr)
        return 1
    
    print("\n✅ FF-01: Pulse 6-tuple consistent.", file=sys.stderr)
    return 0

if __name__ == "__main__":
    sys.exit(main())
