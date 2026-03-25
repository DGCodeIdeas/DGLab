# Decommissioning Plan: Legacy CMS & AdminPanel

## 1. Objective
To systematically remove legacy code, documentation, and architectural patterns from `Base CMS` and `AdminPanel` as they are superseded by the unified **CMS Studio** ecosystem.

## 2. Inventory of Superseded Components

| Legacy Component | New Studio Equivalent | Status |
| :--- | :--- | :--- |
| **Base CMS Core** | CMS Studio Hub + Hybrid EAV | Ready for Migration |
| **Admin Control Panel** | Pulse App + Identity App (IAM) | Ready for Migration |
| **Legacy .php Views** | SuperPHP .super.php Components | Migration in Progress |
| **Static JS/SCSS** | AssetBundler Pure PHP Bundling | **COMPLETED** |

## 3. Phased Decommissioning Strategy

### Phase A: Knowledge Transfer (COMPLETED)
- [x] All relevant goals from `Blueprint/CMS/` and `Blueprint/AdminPanel/` have been merged into `Blueprint/CMSStudio/`.
- [x] Technical requirements for IAM, Tenancy, and Observability have been losslessly refactored.

### Phase B: Logic Migration & Bridging
- [ ] Identify any surviving core logic in `app/Services/CMS` or `app/Services/Admin` (e.g., custom database drivers).
- [ ] Refactor surviving logic to extend `BaseService` and integrate with `AuditService`.
- [ ] Move refactored logic into the `CMSStudio` namespace.

### Phase C: View Conversion
- [ ] Convert all remaining `.php` views in `resources/views/cms` and `resources/views/admin` to SuperPHP `.super.php`.
- [ ] Replace custom CSS/JS with `s:ui` components and Tailwind utilities.

### Phase D: Final Purge
- [ ] Remove the `Blueprint/CMS/` directory.
- [ ] Remove the `Blueprint/AdminPanel/` directory.
- [ ] Delete legacy folders: `app/Services/CMS`, `app/Services/Admin`, `resources/views/cms`, `resources/views/admin`.
- [ ] Update `routes/web.php` to point exclusively to CMS Studio controllers.

## 4. Safety & Verification
- **Audit Trail**: Every deletion or refactor must be logged in the `AuditService`.
- **Unit Testing**: Existing tests for CMS/Admin logic must be updated to target the new CMS Studio implementation.
- **Pure Superpowers**: Ensure zero legacy artifacts (e.g., `public/js/legacy.js`) remain after Phase D.

## 5. Timeline
Decommissioning will occur incrementally as each corresponding Studio App (Identity, Architect, Pulse, etc.) reaches Phase-Complete status.
