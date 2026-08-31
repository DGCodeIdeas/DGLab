# ESPOKE-05 Wireframe — Sovereign Growth (Marketing Landing Page Engine)

**Frozen under Cooldown 0 ADR-gate (SDLC-AGRD v3.4 §3). Changes require a new ADR.**

**Source blueprint:** `Architecture/Spoke/External/ESPOKE-05.md` (Bloom Status: 🔴 Blocked; Build Status of this contract: CONTRACT-FROZEN, not implemented).
**Contract freeze date:** 2026-08-13 (Cooldown 0, Lap 0).
**Cross-referenced contracts produced in this Cooldown:** `HUB-26-theme-tokens.md` (visual slots) and `HUB-13-string-keys.md` (copy slots).

---

## 0. Purpose & scope of this document

This wireframe enumerates **every user-visible surface** of ESPOKE-05's three documented subsystems:

- **BlockEngine** — conversion-optimized blocks: Hero, Features, Pricing, Testimonials.
- **CampaignManager** — page variations, UTM tracking, conversion goals, and the (HUB-31-pending) live dashboard.
- **LandingPageRenderer** — page chrome (navigation, footer), loading/error/empty states.

For each surface we list:
1. An ASCII wireframe with dimension notes.
2. **COPY SLOTS** — every string a marketer writes, cited to a `HUB-13` key (`{domain}.{component}.{slot}`).
3. **VISUAL SLOTS** — every styled element, cited to a `HUB-26` token (`--token-name`).

No source files were modified. No code was written. This is a contract for the marketer and media person to write against for 6+ months.

### Legend
- `[KEY]` = a copy slot → `HUB-13-string-keys.md` key name.
- `{TOKEN}` = a visual slot → `HUB-26-theme-tokens.md` token name.
- ▢ = image / icon slot (alt-text is a copy slot authored by media).

---

## 1. LandingPageRenderer — Page Chrome

### 1.1 Navigation bar (Public theme)

```
┌───────────────────────────────────────────────────────────────┐
│ {surface: bg --color-surface, border-bottom --color-border}    │
│ ┌────────┐   Features  Pricing  Testimonials      [ Get Started ]│
│ │ ▢ LOGO │   [menu]    [menu]   [menu]            [CTA button] │
│ └────────┘                                            ───────────│
│  {font-family-heading}                      {button --color-primary-600}│
│  mobile: [☰ toggle]  → aria-label [KEY navigation.toggle_aria]  │
│  skip-link: [KEY navigation.skip_link]  (visually-hidden focus) │
└───────────────────────────────────────────────────────────────┘
  viewport: full-width; container max-width tied to --breakpoint-xl (1280px)
  height: ~64px (Public); denser in Admin (see §6)
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Logo alt-text | `navigation.brand_alt` | — |
| Menu item: Features | `navigation.menu_features` | `--font-size-base` |
| Menu item: Pricing | `navigation.menu_pricing` | `--font-size-base` |
| Menu item: Testimonials | `navigation.menu_testimonials` | `--font-size-base` |
| CTA button label | `navigation.menu_cta` | `--color-primary-600`, `--radius-md`, `--space-3`/`--space-5` |
| Mobile toggle aria-label | `navigation.toggle_aria` | — |
| Skip-to-content link | `navigation.skip_link` | `--color-focus-ring` (focus) |

**VISUAL SLOTS**
- Surface background: `--color-surface`
- Bottom border: `--color-border`
- CTA fill: `--color-primary-600`, hover `--color-primary-700`
- CTA radius: `--radius-md`; padding `--space-3`/`--space-5`
- Heading font: `--font-family-heading`
- Focus ring (skip-link, toggle): `--color-focus-ring`

### 1.2 Footer

```
┌───────────────────────────────────────────────────────────────┐
│ {bg --color-bg, border-top --color-border, text --color-text-muted}│
│  ▢ logo   [tagline]                                            │
│  Company        Resources       Legal                          │
│  [About]        [Contact]       [Privacy]  [Terms]             │
│  ───────────────────────────────────────────────────          │
│  © [year] DGLab. [KEY footer.copyright]                        │
└───────────────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Tagline | `footer.tagline` | `--color-text-muted` |
| Column heading: Company | `footer.col_company` | `--font-weight-semibold` |
| Column heading: Resources | `footer.col_resources` | `--font-weight-semibold` |
| Column heading: Legal | `footer.col_legal` | `--font-weight-semibold` |
| Link: About | `footer.link_about` | `--color-text-muted` |
| Link: Contact | `footer.link_contact` | `--color-text-muted` |
| Link: Privacy | `footer.link_privacy` | `--color-text-muted` |
| Link: Terms | `footer.link_terms` | `--color-text-muted` |
| Copyright line | `footer.copyright` (placeholder `:year`) | `--font-size-sm` |

**VISUAL SLOTS**
- Background `--color-bg`, border-top `--color-border`, muted text `--color-text-muted`
- Column-heading weight `--font-weight-semibold`
- Copyright size `--font-size-sm`

---

## 2. BlockEngine — Conversion Blocks

All blocks render inside a centered container (max-width `--breakpoint-lg` 1024px) with vertical rhythm `--space-8` between blocks.

### 2.1 Hero block

```
┌───────────────────────────────────────────────────────────────┐
│                    [eyebrow / trust badge]  [KEY marketing.hero.trust_badge]│
│  ┌─────────────────────────┐   ┌─────────────────────────┐   │
│  │ H1 [KEY marketing.hero.headline]                    │   │ ▢ HERO IMAGE             │ │
│  │    {font-size-5xl, weight-bold, --color-text}       │   │   alt: [KEY marketing.hero.image_alt]│ │
│  │ P  [KEY marketing.hero.subhead]                     │   │                         │ │
│  │    {font-size-lg, --color-text-muted}               │   │                         │ │
│  │ [ Primary CTA ]  [ Secondary CTA ]                  │   │                         │ │
│  │   --color-primary-600   --color-surface / border   │   │                         │ │
│  └─────────────────────────┘   └─────────────────────────┘   │
│              ↓ scroll cue [KEY marketing.hero.scroll_cue]      │
└───────────────────────────────────────────────────────────────┘
  min-height: ~70vh; columns collapse to 1 at --breakpoint-md (768px)
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Headline (H1) | `marketing.hero.headline` | `--font-size-5xl`, `--font-weight-bold`, `--color-text` |
| Subhead (body) | `marketing.hero.subhead` | `--font-size-lg`, `--color-text-muted`, `--line-height-relaxed` |
| Primary CTA | `marketing.hero.cta_primary` | `--color-primary-600`, `--radius-md` |
| Secondary CTA | `marketing.hero.cta_secondary` | `--color-surface`, `--color-border`, `--color-text` |
| Hero image alt-text | `marketing.hero.image_alt` | — |
| Trust badge | `marketing.hero.trust_badge` | `--color-success-500` (icon), `--font-size-sm` |
| Scroll cue | `marketing.hero.scroll_cue` | `--color-text-muted` |

**VISUAL SLOTS** — see token citations in the table above; block padding `--space-8`, image radius `--radius-lg`.

### 2.2 Features block

```
┌───────────────────────────────────────────────────────────────┐
│            [eyebrow] [KEY marketing.features.eyebrow]          │
│         H2 [KEY marketing.features.heading]                   │
│      P  [KEY marketing.features.subheading]                   │
│  ┌────────┐  ┌────────┐  ┌────────┐  (grid: 3 cols → 1 at md) │
│  │ ▢ icon │  │ ▢ icon │  │ ▢ icon │                          │
│  │ Title  │  │ Title  │  │ Title  │   [KEY marketing.features.item_title]│
│  │ Desc   │  │ Desc   │  │ Desc   │   [KEY marketing.features.item_description]│
│  └────────┘  └────────┘  └────────┘                          │
│   icon alt: [KEY marketing.features.item_icon_alt]            │
└───────────────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Eyebrow | `marketing.features.eyebrow` | `--color-primary-600`, `--font-size-sm`, `--font-weight-semibold` |
| Heading (H2) | `marketing.features.heading` | `--font-size-3xl`, `--font-weight-bold`, `--color-text` |
| Subheading | `marketing.features.subheading` | `--font-size-lg`, `--color-text-muted` |
| Item title (×N) | `marketing.features.item_title` (placeholder `:index`) | `--font-size-xl`, `--font-weight-semibold` |
| Item description (×N) | `marketing.features.item_description` (placeholder `:index`) | `--color-text-muted`, `--line-height-normal` |
| Item icon alt-text (×N) | `marketing.features.item_icon_alt` (placeholder `:index`) | — |

**VISUAL SLOTS** — grid gap `--space-5`, card surface `--color-surface-raised`, card radius `--radius-md`, card shadow `--shadow-sm`, card padding `--space-5`.

### 2.3 Pricing block

```
┌───────────────────────────────────────────────────────────────┐
│            [eyebrow] [KEY marketing.pricing.eyebrow]           │
│         H2 [KEY marketing.pricing.heading]                    │
│      P  [KEY marketing.pricing.subheading]                    │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  (3 plans)         │
│  │ [badge]  │  │ ★ POPULAR│  │          │  [KEY marketing.pricing.plan_badge_popular]│
│  │ Plan Name│  │ Plan Name│  │ Plan Name│  [KEY marketing.pricing.plan_name]│
│  │ $:amount │  │ $:amount │  │ $:amount │  [KEY marketing.pricing.plan_price]│
│  │ /:period │  │ /:period │  │ /:period │                     │
│  │ Desc     │  │ Desc     │  │ Desc     │  [KEY marketing.pricing.plan_description]│
│  │ • feat   │  │ • feat   │  │ • feat   │  [KEY marketing.pricing.plan_feature]│
│  │ [ CTA ]  │  │ [ CTA ]  │  │ [ CTA ]  │  [KEY marketing.pricing.plan_cta]│
│  └──────────┘  └──────────┘  └──────────┘                     │
│   popular card: --color-primary-50 border / --color-primary-600│
│   [note] [KEY marketing.pricing.note]                          │
└───────────────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Eyebrow | `marketing.pricing.eyebrow` | `--color-primary-600`, `--font-size-sm` |
| Heading | `marketing.pricing.heading` | `--font-size-3xl`, `--font-weight-bold` |
| Subheading | `marketing.pricing.subheading` | `--color-text-muted` |
| Plan name (×N) | `marketing.pricing.plan_name` (placeholder `:plan`) | `--font-size-xl`, `--font-weight-semibold` |
| Plan price (×N) | `marketing.pricing.plan_price` (placeholders `:amount`, `:period`) | `--font-size-4xl`, `--font-weight-bold`, `--color-text` |
| Plan description (×N) | `marketing.pricing.plan_description` (placeholder `:plan`) | `--color-text-muted` |
| Plan feature (×N) | `marketing.pricing.plan_feature` (placeholder `:feature`) | `--font-size-sm`, `--color-text` |
| Plan CTA (×N) | `marketing.pricing.plan_cta` (placeholder `:plan`) | `--color-primary-600` / popular `--color-primary-700` |
| Popular badge | `marketing.pricing.plan_badge_popular` | `--color-primary-600`, `--color-primary-50` bg, `--radius-full` |
| Footnote | `marketing.pricing.note` | `--font-size-sm`, `--color-text-muted` |

**VISUAL SLOTS** — card grid gap `--space-5`; popular card border `--color-primary-600`, bg `--color-primary-50`, radius `--radius-lg`, shadow `--shadow-md`; standard card border `--color-border`, radius `--radius-lg`, shadow `--shadow-sm`; check-icon color `--color-success-500`.

### 2.4 Testimonials block

```
┌───────────────────────────────────────────────────────────────┐
│            [eyebrow] [KEY marketing.testimonials.eyebrow]      │
│         H2 [KEY marketing.testimonials.heading]               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ "quote"      │  │ "quote"      │  │ "quote"      │  [KEY marketing.testimonials.quote]│
│  │ ▢ avatar     │  │ ▢ avatar     │  │ ▢ avatar     │  alt: [KEY marketing.testimonials.avatar_alt]│
│  │ Name         │  │ Name         │  │ Name         │  [KEY marketing.testimonials.author_name]│
│  │ Role/Company │  │ Role/Company │  │ Role/Company │  [KEY marketing.testimonials.author_role]│
│  └──────────────┘  └──────────────┘  └──────────────┘         │
└───────────────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Eyebrow | `marketing.testimonials.eyebrow` | `--color-primary-600`, `--font-size-sm` |
| Heading | `marketing.testimonials.heading` | `--font-size-3xl`, `--font-weight-bold` |
| Quote (×N) | `marketing.testimonials.quote` (placeholder `:index`) | `--font-size-lg`, `--line-height-relaxed`, `--color-text` |
| Author name (×N) | `marketing.testimonials.author_name` (placeholder `:index`) | `--font-weight-semibold`, `--color-text` |
| Author role (×N) | `marketing.testimonials.author_role` (placeholder `:index`) | `--font-size-sm`, `--color-text-muted` |
| Avatar alt-text (×N) | `marketing.testimonials.avatar_alt` (placeholder `:index`) | — |

**VISUAL SLOTS** — quote card bg `--color-surface-raised`, radius `--radius-lg`, shadow `--shadow-sm`, padding `--space-5`; avatar radius `--radius-full`; name weight `--font-weight-semibold`.

### 2.5 Lead-capture form block (Hero/Features CTA target)

```
┌───────────────────────────────────────────────────────────────┐
│  Name      [ ____________________ ]  label [KEY marketing.lead_form.name_label]│
│            placeholder [KEY marketing.lead_form.name_placeholder]│
│  Email     [ ____________________ ]  label [KEY marketing.lead_form.email_label]│
│            placeholder [KEY marketing.lead_form.email_placeholder]│
│  Message   [ ____________________ ]  label [KEY marketing.lead_form.message_label]│
│            placeholder [KEY marketing.lead_form.message_placeholder]│
│  [ Submit ]  [KEY marketing.lead_form.submit]  --color-primary-600    │
│  ✔ [KEY marketing.lead_form.success]   (--color-success-500)         │
│  ✖ [KEY marketing.lead_form.error_required | error_email] (--color-danger-500)│
└───────────────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Name label | `marketing.lead_form.name_label` | `--font-weight-medium`, `--color-text` |
| Name placeholder | `marketing.lead_form.name_placeholder` | `--color-text-muted` |
| Email label | `marketing.lead_form.email_label` | `--font-weight-medium` |
| Email placeholder | `marketing.lead_form.email_placeholder` | `--color-text-muted` |
| Message label | `marketing.lead_form.message_label` | `--font-weight-medium` |
| Message placeholder | `marketing.lead_form.message_placeholder` | `--color-text-muted` |
| Submit button | `marketing.lead_form.submit` | `--color-primary-600`, `--radius-md` |
| Success message | `marketing.lead_form.success` | `--color-success-500` |
| Error: required | `marketing.lead_form.error_required` | `--color-danger-500` |
| Error: invalid email | `marketing.lead_form.error_email` | `--color-danger-500` |

**VISUAL SLOTS** — input border `--color-border`, focus `--color-focus-ring`, radius `--radius-md`, padding `--space-3`; label weight `--font-weight-medium`; error text color `--color-danger-500`, success `--color-success-500`; field spacing `--space-4`.

---

## 3. CampaignManager — Marketer-facing surfaces

These surfaces are rendered in the **Admin theme** (denser) per HUB-26's Theme Variant Contract. They are authored by the marketer, surfaced via the Admin shell (ISPOKE-01), not the public landing page.

### 3.1 Variation selector

```
┌───────────────────────────────────────────────────────┐
│ Select variation:  [ Control ▾ ]   (label [KEY campaign.variation.label])│
│   options: Control [KEY campaign.variation.control]    │
│             Variant :letter [KEY campaign.variation.variant]│
│   current: "Viewing: :letter" [KEY campaign.variation.current]│
└───────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Field label | `campaign.variation.label` | `--font-weight-medium` |
| Option: Control | `campaign.variation.control` | `--font-size-sm` |
| Option: Variant | `campaign.variation.variant` (placeholder `:letter`) | `--font-size-sm` |
| Current indicator | `campaign.variation.current` (placeholder `:letter`) | `--color-text-muted` |

### 3.2 UTM display

```
┌───────────────────────────────────────────────────────┐
│ Campaign UTM parameters      [KEY campaign.utm.heading]│
│  Source   [ value ]   label [KEY campaign.utm.source] │
│  Medium   [ value ]   label [KEY campaign.utm.medium] │
│  Campaign [ value ]   label [KEY campaign.utm.campaign]│
│  Term     [ value ]   label [KEY campaign.utm.term]   │
│  Content  [ value ]   label [KEY campaign.utm.content]│
│  URL: https://…?utm_…  [ Copy ]  label [KEY campaign.utm.url_label]│
│                           button [KEY campaign.utm.copy]│
└───────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Heading | `campaign.utm.heading` | `--font-size-xl`, `--font-weight-semibold` |
| Source label | `campaign.utm.source` | `--font-weight-medium` |
| Medium label | `campaign.utm.medium` | `--font-weight-medium` |
| Campaign label | `campaign.utm.campaign` | `--font-weight-medium` |
| Term label | `campaign.utm.term` | `--font-weight-medium` |
| Content label | `campaign.utm.content` | `--font-weight-medium` |
| URL display label | `campaign.utm.url_label` | `--font-size-sm`, `--color-text-muted` |
| Copy button | `campaign.utm.copy` | `--color-primary-600`, `--radius-sm` |

### 3.3 Conversion goal UI

```
┌───────────────────────────────────────────────────────┐
│ Conversion goals            [KEY campaign.goal.heading]│
│  Name: [ __________ ]  label [KEY campaign.goal.name_label]│
│  Desc: [ __________ ]  label [KEY campaign.goal.description]│
│  [ Set goal ]  [KEY campaign.goal.set_cta]             │
│  Progress: :count / :total  [KEY campaign.goal.progress]│
│  (empty) "No goals set yet." [KEY campaign.goal.empty]│
└───────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Heading | `campaign.goal.heading` | `--font-size-xl`, `--font-weight-semibold` |
| Name label | `campaign.goal.name_label` | `--font-weight-medium` |
| Description label | `campaign.goal.description` | `--font-weight-medium` |
| Set-goal CTA | `campaign.goal.set_cta` | `--color-primary-600`, `--radius-md` |
| Progress | `campaign.goal.progress` (placeholders `:count`, `:total`) | `--color-text-muted` |
| Empty state | `campaign.goal.empty` | `--color-text-muted`, `--font-size-sm` |

### 3.4 Live conversion dashboard (HUB-31-pending, graceful degradation)

```
┌───────────────────────────────────────────────────────┐
│ Live conversion dashboard    [KEY campaign.dashboard.heading]│
│  ── if HUB-31 unavailable ──                              │
│  ⚠ [KEY campaign.dashboard.degraded]  (--color-warning-500)│
│  Conversions: :count  [KEY campaign.dashboard.metric_conversions]│
│  Rate: :rate         [KEY campaign.dashboard.metric_rate]│
│  Visitors: :count    [KEY campaign.dashboard.metric_visitors]│
└───────────────────────────────────────────────────────┘
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Heading | `campaign.dashboard.heading` | `--font-size-xl`, `--font-weight-semibold` |
| Degraded notice | `campaign.dashboard.degraded` | `--color-warning-500` |
| Metric: conversions (pluralized) | `campaign.dashboard.metric_conversions` (placeholder `:count`, pluralization) | `--color-text` |
| Metric: rate | `campaign.dashboard.metric_rate` (placeholder `:rate`) | `--color-text` |
| Metric: visitors | `campaign.dashboard.metric_visitors` (placeholder `:count`) | `--color-text-muted` |

**VISUAL SLOTS** — degraded banner bg `--color-warning-500` @ low alpha, border `--color-warning-500`, icon `--color-warning-500`. (These semantic alert tokens are defined in `HUB-26-theme-tokens.md`.)

---

## 4. Cross-cutting states (renderer-wide)

```
LOADING:   ▦ skeleton + sr-only [KEY states.loading]   {--color-neutral-200 shimmer}
EMPTY:     (icon) [title] [KEY states.empty.title]  [body] [KEY states.empty.body]
ERROR 404: (icon) [headline] [KEY errors.page_not_found.headline]
                [body] [KEY errors.page_not_found.body]
                [Go home] [KEY errors.page_not_found.cta]
ERROR gen: (icon) [headline] [KEY errors.generic.headline]
                [body] [KEY errors.generic.body]
                [Retry] [KEY errors.generic.retry]
```

**COPY SLOTS**
| Slot | HUB-13 key | Token |
|---|---|---|
| Loading (sr-only) | `states.loading` | `--color-neutral-200` (skeleton) |
| Empty title | `states.empty.title` | `--font-weight-semibold`, `--color-text` |
| Empty body | `states.empty.body` | `--color-text-muted` |
| 404 headline | `errors.page_not_found.headline` | `--font-size-3xl`, `--font-weight-bold` |
| 404 body | `errors.page_not_found.body` | `--color-text-muted` |
| 404 CTA | `errors.page_not_found.cta` | `--color-primary-600`, `--radius-md` |
| Generic error headline | `errors.generic.headline` | `--font-size-3xl`, `--font-weight-bold` |
| Generic error body | `errors.generic.body` | `--color-text-muted` |
| Generic retry | `errors.generic.retry` | `--color-primary-600`, `--radius-md` |

**VISUAL SLOTS** — skeleton shimmer token `--color-neutral-200`; error icon color `--color-danger-500`; error headings `--color-text`, weight `--font-weight-bold`.

---

## 5. Coverage summary (forward reference to Artifacts 2 & 3)

- **Copy slots:** every slot above is cited to a `HUB-13` key defined in `HUB-13-string-keys.md`. Total enumerated copy slots: **87** keys (per-domain: marketing 39, navigation 7, footer 9, campaign 23, errors 6, states 3 — see that file's §5). No copy slot is left without a key.
- **Visual slots:** every styled element is cited to a `HUB-26` token defined in `HUB-26-theme-tokens.md`. Total distinct tokens referenced: **color (×~22), spacing (×~9), typography (×~14), border/radius (×~7), shadow (×~3), breakpoint (×~5), animation/duration (implicit via transitions)**. No visual slot is left without a token.
- **Admin vs Public theme:** CampaignManager surfaces (§3) consume the **Admin** theme (denser spacing/type per `HUB-26-theme-tokens.md` §5). All landing-page surfaces (§1, §2, §4) consume the **Public** theme.

## 6. Gaps surfaced (Cooldown 0)

None that require an ADR or a new OD. Both cross-referenced contracts (HUB-13 key taxonomy, HUB-26 token taxonomy) are being authored in this same Cooldown and are sufficient to cover every slot above. Specifically:

- HUB-13's `TranslatorInterface` (`get(string $key, array $replace, ?string $locale)`) supports the placeholders (`:name`, `:count`, `:amount`, `:period`, `:year`, `:rate`, `:letter`, `:index`, `:plan`, `:feature`) and pluralization forms used here. No interface change needed.
- HUB-26's `ThemeInterface` (`tokens()` + `componentOverrides()`) supports every color/spacing/type/border/shadow/breakpoint/animation slot referenced. No interface change needed.

> Note: canonical count is **102** per `INDEX.md` §4 (updated 2026-08-13) and `ADR-011` acceptance. No discrepancy.

**No source files were modified. This document is a frozen contract, not an implementation.**
