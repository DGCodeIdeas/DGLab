# RUNBOOK-MIGRATION-PROVIDER.md

## Migration Provider Activation and Recovery

### Providers

| Provider | Config value | Status |
|----------|-------------|--------|
| Doctrine (temporary) | `doctrine` | Active (Pass 1) |
| Native (Revision 5.3) | `native` | Not yet implemented |

### Activation (Doctrine)

1. Ensure `doctrine/dbal` is installed (`composer require doctrine/dbal`).
2. Set `migration.runner = doctrine` in application config.
3. Ensure MySQL credentials are configured (env vars: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).
4. Run `php cli/migrate.php status` to verify pending migrations.
5. Run `php cli/migrate.php migrate` to apply.

### Switching providers

**Preconditions for config-only switch:**
- Both providers and their dependencies must already be deployed.
- Config must be reloadable without a code deploy.
- Database state must be compatible between providers (same tracking table schema).
- Both providers must use the same `dglab_migrations` tracking table.

**If the switch fails:**
1. Revert config to the previous provider.
2. Reload config.
3. Verify with `php cli/migrate.php status`.
4. If DB state is inconsistent, manually reconcile the tracking table.

### Recovery from failed migration

1. Check status: `php cli/migrate.php status`
2. Identify the failed version.
3. Manually inspect the database state.
4. If DDL partially applied, manually complete or revert the DDL.
5. Update the tracking table manually if needed.
6. Re-run `php cli/migrate.php migrate` after resolution.

### Rollback

Rollback is NOT supported by the Doctrine adapter. MySQL DDL implicitly
commits and cannot be transactionally rolled back. Manual intervention
is required for any rollback scenario.
