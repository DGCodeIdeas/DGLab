# DEPLOY-01: Free Tier Render Docker Deployment

## Tier
Infrastructure (Deployment & Hosting)

## Component Name
Render Free Tier Docker Deployment Pipeline

## Description
A production-ready Docker deployment configuration for Render's free tier, optimizing for zero-cost operation while maintaining reliability and performance. This blueprint provides the foundation for deploying the DGLab architecture on Render with automatic scaling constraints, health checks, and environment isolation.

## Sequencing Rationale
This deployment blueprint should be implemented after the core application is containerized and before introducing paid infrastructure scaling. It establishes the baseline deployment pattern that all future infrastructure decisions (paid tiers, multi-region, CDN) will build upon.

## Context & Research
- **Target Platform**: Render (render.com) Free Tier
- **Container Runtime**: Docker with PHP 8.3
- **Environment Constraints**: 
  - Free tier provides 750 hours/month (sufficient for always-on service)
  - Automatic spin-down after 15 minutes of inactivity (cold starts acceptable)
  - 512MB memory, shared CPU
  - 100GB bandwidth/month included
- **Base Application**: PHP 8.3 CLI serving documentation/blueprints via built-in web server
- **Architecture Pattern**: Extends deployment across free tier resources while maintaining extensibility

## Architectural Design

### Container Image Strategy
- **Base Image**: `php:8.3-cli` - Lightweight, optimized for low-memory footprint
- **Dependencies**: Git, Zip/Unzip, Composer for dependency management
- **Port Binding**: Port 80 (standard HTTP) with explicit `EXPOSE` declaration
- **Working Directory**: `/app` - Isolated application space
- **Startup Command**: PHP built-in web server serving documentation directory

### Render Configuration
```yaml
services:
  - type: web
    name: sovereign-stack-blueprints
    runtime: docker
    plan: free
    region: oregon
    envVars:
      - key: PORT
        value: 80
```

### Key Parameters Explained

| Parameter | Value | Rationale |
|-----------|-------|-----------|
| `type: web` | Web service | Render infers HTTP routing and port mapping |
| `runtime: docker` | Docker container | Explicit control over environment and dependencies |
| `plan: free` | Free tier | Zero cost; acceptable for non-critical documentation service |
| `region: oregon` | Geographic location | Render's primary free tier region; lowest latency for North America |
| `PORT` env var | 80 | Standard HTTP port; Render forwards external HTTPS to container port 80 |

## Deployment Artifacts

### File: `Dockerfile`
```dockerfile
FROM php:8.3-cli

RUN apt-get update && apt-get install -y git zip unzip
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Expose the port Render expects
EXPOSE 80

# Serve the Architecture directory using PHP's built-in web server
# This keeps the process alive and makes blueprints viewable
CMD ["php", "-S", "0.0.0.0:80", "-t", "Architecture"]
```

### File: `render.yaml`
```yaml
services:
  - type: web
    name: sovereign-stack-blueprints
    runtime: docker
    plan: free
    region: oregon
    envVars:
      - key: PORT
        value: 80
```

### File: `.renderignore` (Optional but Recommended)
```
.git
.gitignore
node_modules
*.log
.env.local
blueprints.disapproved
Legacy.old
```

## Integration Strategy

### Upward (Consumed By)
- **GitHub Actions**: CI/CD pipelines that trigger deployments on push to main branch
- **DNS Configuration**: Custom domain pointing to Render service URL
- **SSL/TLS**: Render provides automatic HTTPS via Let's Encrypt

### Downward (Consumes)
- **Docker Hub**: Pulls base images (`php:8.3-cli`, `composer`)
- **GitHub Repository**: Source code reference for deployment
- **Environment Variables**: Port configuration and future secrets management

### Health Checks
Render automatically monitors service health. For production use:
- **HTTP Health Endpoint**: `/ping` or similar lightweight response
- **Startup Probe**: 30-second grace period for application boot
- **Liveness Probe**: Check every 30 seconds

## Performance Characteristics

### Cold Start Behavior
- **First Request After Idle**: 15-30 seconds (acceptable for documentation service)
- **Mitigation**: Render provides "Keep Alive" functionality; configure ping from external service or CDN
- **Expected Impact**: Minimal; documentation is read-heavy with predictable traffic patterns

### Memory Profile
- **Base Image Size**: ~120MB (PHP 8.3 CLI minimal)
- **Runtime Memory**: 100-150MB with application loaded
- **Free Tier Limit**: 512MB
- **Headroom**: 350-400MB available for application growth

### Network Performance
- **Outbound Bandwidth**: 100GB/month free allocation
- **Typical Usage**: 0.5-2GB/month for API documentation and blueprints
- **Scaling**: Monitor bandwidth; upgrade to paid tier if exceeds allocation

## Deployment Workflow

### Prerequisites
1. GitHub repository connected to Render account
2. Dockerfile present in repository root
3. `render.yaml` present in repository root
4. PHP application ready for containerization

### Deployment Steps
1. **Push to GitHub**: Commit changes to main branch
2. **Render Auto-Deploy**: GitHub webhook triggers Render build
3. **Image Build**: Docker image built from Dockerfile
4. **Service Deploy**: Container started with `render.yaml` configuration
5. **Health Check**: Render validates service responding on port 80
6. **DNS Update**: Render URL updated (if using Render domain)

### Rollback Procedure
- **Automatic**: Previous deployment available for one-click rollback
- **Manual**: Revert code push on GitHub; Render redeploys previous image
- **Time to Restore**: ~2-3 minutes from rollback initiation

## CI Verification Criteria

### Build Verification
- ✅ Dockerfile syntax valid (no build errors)
- ✅ All dependencies resolve (Composer installs successfully)
- ✅ Image builds under 5 minutes
- ✅ Image size under 500MB

### Runtime Verification
- ✅ Container starts within 10 seconds
- ✅ PHP web server listens on port 80
- ✅ HTTP requests return 200-class responses
- ✅ Documentation/blueprints accessible at root path
- ✅ Static files serve with appropriate cache headers
- ✅ No application errors in logs

### Performance Verification
- ✅ Response time: <500ms for documentation pages
- ✅ Memory usage: <250MB under normal load
- ✅ CPU utilization: <30% during traffic spikes
- ✅ Uptime: >99% over 30-day period

## Monitoring & Observability

### Render Dashboard Metrics
- **Deployment Status**: View real-time deployment progress
- **Service Health**: Monitor uptime and restart history
- **Memory Usage**: Track memory consumption patterns
- **CPU Usage**: Identify performance bottlenecks
- **Logs**: Access application output for debugging

### Recommended Monitoring Additions
1. **External Uptime Monitoring**: Use Uptime Robot or similar for alerts
2. **Error Tracking**: Implement Sentry or similar error aggregation
3. **Application Logging**: Send logs to external service (e.g., LogRocket, Loggly)
4. **Performance Monitoring**: Enable Render's built-in metrics dashboard

## Scaling Path (Future)

### Free Tier to Paid Tier
When requirements exceed free tier:
1. **More Memory**: Upgrade to Standard tier (2GB RAM)
2. **Guaranteed Uptime**: Paid tier removes idle shutdown
3. **Multiple Instances**: Horizontal scaling with load balancing
4. **Managed Databases**: PostgreSQL, MySQL, Redis integration

### Multi-Region Deployment
1. Deploy application to multiple Render regions
2. Use Render's built-in networking or external CDN
3. Implement health-check based failover
4. Distribute traffic across regions

### Container Optimization
1. Multi-stage builds to reduce image size
2. Alpine base images for minimal footprint
3. Custom PHP build with only required extensions
4. Lazy-loading of heavy dependencies

## SemVer Impact
**Patch**. This is infrastructure configuration with no API surface changes; updates maintain backward compatibility.

## Success Criteria

- ✅ Service accessible at Render-provided URL
- ✅ All blueprints accessible via HTTP
- ✅ Zero downtime deployments working
- ✅ Automatic redeploy on GitHub push operational
- ✅ Logs viewable in Render dashboard
- ✅ Cost remains $0/month
- ✅ Response times consistently <500ms

## Cost Analysis

| Resource | Free Tier | Monthly Cost |
|----------|-----------|--------------|
| Web Service (750 hrs/month) | Included | $0 |
| Bandwidth (100GB/month) | Included | $0 |
| Disk Storage (10GB) | Included | $0 |
| Build Minutes (500/month) | Included | $0 |
| **TOTAL** | | **$0** |

Upgrade costs apply only when exceeding free tier quotas.

## Related Blueprints
- **CORE-01**: Application bootstrap and initialization
- **CORE-20**: Developer CLI and scaffolding
- **HUB-15**: Health checks and service discovery patterns
