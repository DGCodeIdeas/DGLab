# PHASE HUB-30: Hub Developer CLI Toolchain

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10). Completes the Hub tier — `HUB-01` through `HUB-30`
are now all rewritten to the standard defined in `01_MASTER_INDEX.md`.

## Component Name
Sovereign Hub-CLI

## Description
Specialized CLI for Hub administrators/developers, extending `CORE-20` (Forge) with commands for
managing tenants, clearing global caches, inspecting queues, and monitoring service health across the
stack.

## Build Status
🔴 **Blocked** on `HUB-21` (Tenancy), `HUB-15` (Health Check), `HUB-10` (Queue), `HUB-02` (Cache) —
none implemented. As the tier's administrative interface, this is naturally last to build — it has no
value until the components it administers exist.

## Dependency Status
- **Direct Hub:** `HUB-21`, `HUB-15`, `HUB-10`, `HUB-02`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-13`, `CORE-20`.

## Architectural Design
- **TenantManagerCommand** — create/suspend/migrate tenants via `HUB-21`'s `TenancyInterface`.
- **PulseMonitorCommand** — real-time health dashboard from `HUB-15`.
- **QueueInspectorCommand** — view/retry/purge jobs via `HUB-10`.
- **AssetManagerCommand** — triggers Hub-level asset compilation/deployment (`HUB-03`).

```php
class CreateTenantCommand extends Command
{
    protected string $signature = 'hub:tenant:create {name} {domain}';

    public function handle(TenancyInterface $nexus): int
    {
        $tenant = $nexus->create([
            'name' => $this->argument('name'),
            'domain' => $this->argument('domain')
        ]);

        $this->info("Tenant created with ID: {$tenant->id}");
        return 0;
    }
}
```

## Interface Contracts
Inherits from `CORE-13` and `CORE-20`; no new interface surface of its own beyond individual
`Command` subclasses.

## Integration Strategy
- **Upward:** plugs into the `s-cli` entry point.
- **Downward:** used by DevOps/Hub administrators.
- **Contract:** every command supports `--json` output for scripting/automation.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Command discovery | Integration test: run `s-cli list hub`, assert output includes every registered Hub command by name — a count-based assertion (`>= 30`) is weaker than an explicit name-list assertion; use the latter so a renamed/dropped command is caught, not just a count drift. |
| Destructive-command safety | Integration test: invoke `hub:cache:clear` and `hub:tenant:delete` without `--force` in a non-interactive context; assert both refuse to proceed rather than silently completing. |
| Help documentation completeness | Static check: every registered command class has a non-empty description and at least one usage example in its help text — enforced at CI time, not left to reviewer diligence. |

## CI Verification Criteria
- Named command-discovery test (not count-only), blocking.
- Destructive-command safety test for every command tagged destructive, blocking.
- Help-documentation completeness static check, blocking.

## SemVer Impact
**Major.** Completes the Hub tier and provides the operational control plane.
