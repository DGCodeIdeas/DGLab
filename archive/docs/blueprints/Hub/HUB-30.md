# PHASE HUB-30: Hub Developer CLI Toolchain

## Tier
Hub (Shared Services)

## Component Name
Sovereign Hub-CLI

## Description
A specialized CLI toolchain for Hub administrators and developers. It extends `CORE-20` (Forge) with commands for managing tenants, clearing global caches, inspecting queues, and monitoring service health across the entire stack.

## Sequencing Rationale
Final phase of the Hub tier. It provides the administrative interface for all 29 previous Hub phases.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-21: Tenancy`, `HUB-15: Health Check`, `HUB-10: Queue`, `HUB-02: Cache`.
- **Transitive Core Dependencies**: `CORE-13: CLI Engine`, `CORE-20: Developer CLI Toolchain`.
- **UX**: Unified with "The Forge" (`CORE-20`) for a consistent developer experience.

## Architectural Design
- **TenantManagerCommand**: CLI interface for creating, suspending, and migrating tenants.
- **PulseMonitorCommand**: Real-time CLI dashboard for system health (from `HUB-15`).
- **QueueInspectorCommand**: Tools for viewing, retrying, and purging background jobs.
- **AssetManagerCommand**: Triggers Hub-level asset compilation and deployment.

### Implementation Example: Tenant Creation
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
Inherits from `CORE-13` and `CORE-20`.

## Integration Strategy
- **Upward**: Plugs into the `s-cli` entry point defined in `CORE-01`.
- **Downward**: Used by DevOps and Hub administrators to maintain the Sovereign ecosystem.
- **Contract**: All commands must support JSON output (`--json`) for easy piping and automation.

## CI Verification Criteria
- **Command Discovery**: `s-cli list hub` must display all 30+ Hub-specific commands.
- **Safety**: "Destructive" commands (e.g., `cache:clear`, `tenant:delete`) must require a `--force` flag or interactive confirmation.
- **Help Documentation**: Every command must include a detailed description and example usage.

## SemVer Impact
**Major**. Completes the Hub tier and provides the operational control plane.
