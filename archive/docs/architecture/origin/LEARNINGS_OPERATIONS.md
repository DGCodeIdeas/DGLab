# Learnings: DGLab Operations & Deployment

## 1. Node-Free Asset Management
- The `WebpackService` (AssetBundler) effectively replaces Node.js by resolving dependencies and bundling JS/CSS within the PHP runtime. This significantly simplifies the deployment pipeline, as only PHP 8.2+ is required.
- **Action**: Ensure `cli/build-assets.php` is part of the atomic deployment sequence.

## 2. Nexus (Swoole) Lifecycle
- Managing long-running Swoole processes requires Systemd (or similar) for supervision.
- **WebSocket Proxying**: Nginx must be configured to handle the `Upgrade` header for seamless WebSocket connectivity to the Nexus server.
- **Scaling**: Horizontal scaling is achieved via Redis Pub/Sub, which bridges separate Nexus nodes.

## 3. Atomic Deployment via PHP
- The `cli/deploy.php` script serves as a robust orchestrator, handling environment checks, migrations, and health audits.
- **Recommendation**: Always run with `--force` only as a last resort, as health checks are critical for ensuring the "Pure Superpowers" state is consistent.

## 4. Observability & Forensics
- The `AuditService` is the primary tool for post-mortem analysis. By tagging every event with `tenant_id` and `user_id`, we maintain a high-resolution audit trail across all spokes.
- Monolog rotation is essential for preventing disk exhaustion in high-traffic production environments.
