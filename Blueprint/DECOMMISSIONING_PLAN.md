# Decommissioning Plan: Legacy CMS & AdminPanel

## 1. Objective
To systematically remove legacy code, documentation, and architectural patterns from `Base CMS` and `AdminPanel` as they are superseded by the unified **CMS Studio** ecosystem and the **Hub-and-Spoke** architecture.

## 2. Inventory of Superseded Components

| Legacy Component | New Studio Equivalent | Status |
| :--- | :--- | :--- |
| **Base CMS Core** | CMS Studio Hub + Hybrid EAV | Ready for Migration |
| **Admin Control Panel** | Pulse App + Identity App (IAM) | Ready for Migration |
| **Independent Apps** | Domain-Specific Spokes (`app/Spokes/`) | **IN ANALYSIS (PHASE 10)** |
| **Legacy .php Views** | SuperPHP .super.php Components | Migration in Progress |
| **Static JS/SCSS** | AssetBundler Pure PHP Bundling | **COMPLETED** |

## 3. Phased Decommissioning Strategy

### Phase A: Knowledge Transfer (COMPLETED)
- [x] All relevant goals from `Blueprint/CMS/` and `Blueprint/AdminPanel/` have been merged into `Blueprint/CMSStudio/`.

### Phase B: Logic Migration & Hub-and-Spoke Refactor
- [ ] Migrate domain services (MangaScript, EpubFontChanger) to `app/Spokes/`.
- [ ] Refactor surviving logic in `app/Services/CMS` or `app/Services/Admin` to extend `BaseService`.
- [ ] Remove independent routes and controllers for these services.

### Phase C: View Conversion & SPA Unification
- [ ] Convert all remaining `.php` views to SuperPHP `.super.php`.
- [ ] Integrate all UI into the single CMS Studio SPA shell.
- [ ] Ensure all spokes use the shared UI component library.

### Phase D: Final Purge
- [ ] Remove `Blueprint/CMS/` and `Blueprint/AdminPanel/`.
- [ ] Delete legacy folders: `app/Services/CMS`, `app/Services/Admin`, `resources/views/cms`, `resources/views/admin`.
- [ ] Update `routes/web.php` to point exclusively to CMS Studio controllers.

## 4. Verification
- **Audit Trail**: Every deletion or refactor must be logged in the `AuditService`.
- **Pure Superpowers**: Ensure zero legacy artifacts (e.g., `public/js/legacy.js`) remain after Phase D.
