# HUB-13 String Key Taxonomy — Sovereign Translator

**Frozen under Cooldown 0 ADR-gate (SDLC-AGRD v3.4 §3). Changes require a new ADR.**

**Source blueprint:** `Architecture/Hub/HUB-13.md` (Build Status: 🔴 Blocked on CORE-10, HUB-02; this is the *key taxonomy contract*, not an implementation).
**Contract freeze date:** 2026-08-13 (Cooldown 0, Lap 0).
**Companion contracts:** `ESPOKE-05-wireframe.md` (consumes these keys), `HUB-26-theme-tokens.md` (visual companion).

> This document defines the **canonical string-key taxonomy** that HUB-13's `TranslatorInterface::get(string $key, array $replace, ?string $locale)` resolves. It freezes the *content contract* only. HUB-13 is not yet admitted/frozen; this taxonomy is what the marketer writes copy against for 6+ months. No source files were modified.

---

## 1. Naming convention

```
{domain}.{component}.{slot}
```

- `domain` — coarse area: `marketing`, `navigation`, `footer`, `campaign`, `errors`, `states`, `forms` (plus future: `auth`, `common`).
- `component` — the subsystem/block: `hero`, `features`, `pricing`, `testimonials`, `lead_form`, `variation`, `utm`, `goal`, `dashboard`, `brand`, `menu`, `toggle`, `skip_link`, `tagline`, `col_*`, `link_*`, `page_not_found`, `generic`, `loading`, `empty`.
- `slot` — the specific copy role: `headline`, `subhead`, `cta_primary`, `label`, `placeholder`, `title`, `description`, `body`, etc.

**Rules**
1. Lowercase, dot-separated, no spaces. Multi-word slots use underscores.
2. Repeated list items use a `:placeholder` to distinguish instances (e.g. `:index`, `:plan`), NOT separate keys per item — the marketer supplies the per-item value via the placeholder at authoring time, OR the block iterates a content array keyed by index. For block arrays the key stays singular and the value is a template.
3. Every key has a default-English value (the `en` locale source of truth).

## 2. Placeholder & pluralization syntax (per HUB-13 interface)

- **Placeholders:** `:name` style, replaced by `TranslatorInterface::get($key, $replace)`. Allowed placeholders used in this taxonomy: `:name`, `:count`, `:total`, `:amount`, `:period`, `:year`, `:rate`, `:letter`, `:index`, `:plan`, `:feature`.
- **Pluralization:** HUB-13 `Pluralizer` uses the `{0}…|{1}…|[2,*]…` rule syntax (per `HUB-13.md` example). Keys needing pluralization declare it below.

## 3. Fallback chain (explicit)

Documented order (per `HUB-13.md` Fallback-chain verification target):

```
requested locale (e.g. fr-CA)
   → region-stripped parent (fr-CA → fr)
   → default locale (en)
```

- Locale detected from request headers or `HUB-04` session (`HUB-13.md` Integration Strategy).
- A key missing in `fr-CA` falls back to `fr`; if still missing, to `en`. The chain is **explicit and ordered**, never "eventually finds something."
- Spoke-level overrides (ESPOKE-05 marketer copy) sit above Hub defaults in the Loader merge order (`HUB-13.md` Architectural Design: Hub + Spoke directories).

---

## 4. Key catalog

Legend: **P** = supports placeholders · **Z** = pluralized.

### 4.1 `marketing.*` (ESPOKE-05 BlockEngine + lead form)

| Key | Default EN | Context / max len | P | Z |
|---|---|---|---|---|
| `marketing.hero.headline` | `Grow without the busywork` | Hero H1; ~60 chars | | |
| `marketing.hero.subhead` | `The sovereign stack that scales with you.` | Hero body; ~140 chars | | |
| `marketing.hero.cta_primary` | `Get started free` | Primary CTA; ~20 chars | | |
| `marketing.hero.cta_secondary` | `See how it works` | Secondary CTA; ~20 chars | | |
| `marketing.hero.image_alt` | `Illustration of the Sovereign Stack` | Hero image alt-text | | |
| `marketing.hero.trust_badge` | `Trusted by 10,000+ teams` | Eyebrow/trust; ~30 chars | | |
| `marketing.hero.scroll_cue` | `Scroll to explore` | sr/visible cue; ~20 chars | | |
| `marketing.features.eyebrow` | `Capabilities` | Features eyebrow; ~20 chars | | |
| `marketing.features.heading` | `Everything you need to ship` | H2; ~50 chars | | |
| `marketing.features.subheading` | `Powerful blocks, zero boilerplate.` | Sub; ~120 chars | | |
| `marketing.features.item_title` | `Feature :index` | Per-item title template; placeholder `:index` | P | |
| `marketing.features.item_description` | `Describe feature :index here.` | Per-item body template; `:index` | P | |
| `marketing.features.item_icon_alt` | `Icon for feature :index` | Per-item icon alt; `:index` | P | |
| `marketing.pricing.eyebrow` | `Pricing` | Pricing eyebrow; ~15 chars | | |
| `marketing.pricing.heading` | `Simple, transparent pricing` | H2; ~50 chars | | |
| `marketing.pricing.subheading` | `No hidden fees. Cancel anytime.` | Sub; ~80 chars | | |
| `marketing.pricing.plan_name` | `Plan :plan` | Per-plan name template; `:plan` | P | |
| `marketing.pricing.plan_price` | `:amount / :period` | Per-plan price; `:amount`,`:period` | P | |
| `marketing.pricing.plan_description` | `For :plan teams.` | Per-plan desc; `:plan` | P | |
| `marketing.pricing.plan_feature` | `:feature` | Per-feature bullet; `:feature` | P | |
| `marketing.pricing.plan_cta` | `Choose :plan` | Per-plan CTA; `:plan` | P | |
| `marketing.pricing.plan_badge_popular` | `Most popular` | Popular badge; ~15 chars | | |
| `marketing.pricing.note` | `Prices in USD, billed monthly.` | Footnote; ~60 chars | | |
| `marketing.testimonials.eyebrow` | `Testimonials` | Eyebrow; ~15 chars | | |
| `marketing.testimonials.heading` | `Loved by builders` | H2; ~40 chars | | |
| `marketing.testimonials.quote` | `“:index changed how we work.”` | Per-quote template; `:index` | P | |
| `marketing.testimonials.author_name` | `Customer :index` | Per-author; `:index` | P | |
| `marketing.testimonials.author_role` | `Role at Company :index` | Per-role; `:index` | P | |
| `marketing.testimonials.avatar_alt` | `Photo of :index` | Per-avatar alt; `:index` | P | |
| `marketing.lead_form.name_label` | `Full name` | Form label; ~20 chars | | |
| `marketing.lead_form.name_placeholder` | `Jane Doe` | Input placeholder; ~20 chars | | |
| `marketing.lead_form.email_label` | `Email address` | Form label; ~20 chars | | |
| `marketing.lead_form.email_placeholder` | `jane@company.com` | Input placeholder; ~25 chars | | |
| `marketing.lead_form.message_label` | `Message` | Form label; ~20 chars | | |
| `marketing.lead_form.message_placeholder` | `How can we help?` | Textarea placeholder; ~30 chars | | |
| `marketing.lead_form.submit` | `Send message` | Submit button; ~20 chars | | |
| `marketing.lead_form.success` | `Thanks — we'll be in touch.` | Success msg; ~40 chars | | |
| `marketing.lead_form.error_required` | `This field is required.` | Validation; ~30 chars | | |
| `marketing.lead_form.error_email` | `Enter a valid email address.` | Validation; ~30 chars | | |

### 4.2 `navigation.*` (LandingPageRenderer chrome)

| Key | Default EN | Context | P | Z |
|---|---|---|---|---|
| `navigation.brand_alt` | `DGLab Sovereign Stack` | Logo alt-text | | |
| `navigation.menu_features` | `Features` | Nav item; ~12 chars | | |
| `navigation.menu_pricing` | `Pricing` | Nav item; ~12 chars | | |
| `navigation.menu_testimonials` | `Testimonials` | Nav item; ~14 chars | | |
| `navigation.menu_cta` | `Get started` | Nav CTA; ~14 chars | | |
| `navigation.toggle_aria` | `Toggle navigation menu` | Mobile btn aria-label | | |
| `navigation.skip_link` | `Skip to main content` | a11y skip link | | |

### 4.3 `footer.*`

| Key | Default EN | Context | P | Z |
|---|---|---|---|---|
| `footer.tagline` | `The sovereign stack for modern teams.` | Footer tagline; ~50 chars | | |
| `footer.col_company` | `Company` | Column heading; ~12 chars | | |
| `footer.col_resources` | `Resources` | Column heading; ~12 chars | | |
| `footer.col_legal` | `Legal` | Column heading; ~10 chars | | |
| `footer.link_about` | `About` | Link; ~10 chars | | |
| `footer.link_contact` | `Contact` | Link; ~10 chars | | |
| `footer.link_privacy` | `Privacy` | Link; ~10 chars | | |
| `footer.link_terms` | `Terms` | Link; ~10 chars | | |
| `footer.copyright` | `© :year DGLab. All rights reserved.` | Copyright; `:year` | P | |

### 4.4 `campaign.*` (CampaignManager — Admin theme)

| Key | Default EN | Context | P | Z |
|---|---|---|---|---|
| `campaign.variation.label` | `Select variation` | Field label; ~20 chars | | |
| `campaign.variation.control` | `Control` | Option; ~10 chars | | |
| `campaign.variation.variant` | `Variant :letter` | Option; `:letter` | P | |
| `campaign.variation.current` | `Viewing: :letter` | Indicator; `:letter` | P | |
| `campaign.utm.heading` | `Campaign UTM parameters` | Heading; ~25 chars | | |
| `campaign.utm.source` | `Source` | Field label; ~10 chars | | |
| `campaign.utm.medium` | `Medium` | Field label; ~10 chars | | |
| `campaign.utm.campaign` | `Campaign` | Field label; ~12 chars | | |
| `campaign.utm.term` | `Term` | Field label; ~8 chars | | |
| `campaign.utm.content` | `Content` | Field label; ~10 chars | | |
| `campaign.utm.url_label` | `Tracking URL` | URL display label; ~15 chars | | |
| `campaign.utm.copy` | `Copy` | Copy button; ~8 chars | | |
| `campaign.goal.heading` | `Conversion goals` | Heading; ~20 chars | | |
| `campaign.goal.name_label` | `Goal name` | Field label; ~15 chars | | |
| `campaign.goal.description` | `Description` | Field label; ~15 chars | | |
| `campaign.goal.set_cta` | `Set goal` | CTA; ~10 chars | | |
| `campaign.goal.progress` | `:count of :total goals met` | Progress; `:count`,`:total` | P | |
| `campaign.goal.empty` | `No goals set yet.` | Empty state; ~20 chars | | |
| `campaign.dashboard.heading` | `Live conversion dashboard` | Heading; ~25 chars | | |
| `campaign.dashboard.degraded` | `Real-time analytics unavailable — showing last known snapshot.` | Degraded banner; ~60 chars | | |
| `campaign.dashboard.metric_conversions` | `{0} No conversions|{1} One conversion|[2,*] :count conversions` | **Pluralized**; `:count` | P | Z |
| `campaign.dashboard.metric_rate` | `Conversion rate: :rate` | Metric; `:rate` | P | |
| `campaign.dashboard.metric_visitors` | `:count visitors` | Metric; `:count` | P | |

### 4.5 `errors.*` and `states.*` (renderer-wide)

| Key | Default EN | Context | P | Z |
|---|---|---|---|---|
| `errors.page_not_found.headline` | `Page not found` | 404 H1; ~20 chars | | |
| `errors.page_not_found.body` | `The page you're looking for doesn't exist.` | 404 body; ~50 chars | | |
| `errors.page_not_found.cta` | `Go home` | CTA; ~10 chars | | |
| `errors.generic.headline` | `Something went wrong` | Error H1; ~25 chars | | |
| `errors.generic.body` | `An unexpected error occurred. Please try again.` | Error body; ~50 chars | | |
| `errors.generic.retry` | `Retry` | Retry button; ~8 chars | | |
| `states.loading` | `Loading…` | sr-only loading; ~10 chars | | |
| `states.empty.title` | `Nothing here yet` | Empty title; ~20 chars | | |
| `states.empty.body` | `Content will appear here shortly.` | Empty body; ~40 chars | | |

---

## 5. Catalog totals

All keys enumerated in §4. Per-domain counts:

| Domain | Sub-groups | Count |
|---|---|---|
| `marketing` | hero 7, features 6, pricing 10, testimonials 6, lead_form 10 | 39 |
| `navigation` | chrome 7 | 7 |
| `footer` | 9 | 9 |
| `campaign` | variation 4, utm 8, goal 6, dashboard 5 | 23 |
| `errors` | page_not_found 3, generic 3 | 6 |
| `states` | loading 1, empty 2 | 3 |
| **Total** | | **87** |

- **Placeholders used:** `:index`, `:plan`, `:feature`, `:amount`, `:period`, `:letter`, `:count`, `:total`, `:rate`, `:year`.
- **Pluralized keys:** 1 (`campaign.dashboard.metric_conversions`).

## 6. Coverage check against ESPOKE-05-wireframe.md

Every copy slot listed in `ESPOKE-05-wireframe.md` maps to exactly one key above:

- §1 Navigation → `navigation.*` (7 keys) ✔
- §1 Footer → `footer.*` (9 keys) ✔
- §2.1 Hero → `marketing.hero.*` (7 keys) ✔
- §2.2 Features → `marketing.features.*` (6 keys) ✔
- §2.3 Pricing → `marketing.pricing.*` (11 keys) ✔
- §2.4 Testimonials → `marketing.testimonials.*` (6 keys) ✔
- §2.5 Lead form → `marketing.lead_form.*` (11 keys) ✔
- §3.1 Variation → `campaign.variation.*` (4 keys) ✔
- §3.2 UTM → `campaign.utm.*` (9 keys) ✔
- §3.3 Goals → `campaign.goal.*` (6 keys) ✔
- §3.4 Dashboard → `campaign.dashboard.*` (4 keys, incl. pluralized) ✔
- §4 States/Errors → `errors.*` (6) + `states.*` (3) ✔

No wireframe copy slot is left without a key. **No HUB-13 interface change is required** — `get($key, $replace, $locale)` plus placeholder replacement and the `{n}` pluralization syntax are sufficient for every slot, including pluralization (`campaign.dashboard.metric_conversions`).

## 7. Gaps surfaced (Cooldown 0)

None requiring an ADR or new OD. The taxonomy fully covers ESPOKE-05's documented surfaces. Future spokes may extend with new domains (`auth`, `common`) — that is a Cooldown-scope expansion for the next cooldown, not a frozen-interface change.

**No source files were modified. This document is a frozen contract, not an implementation.**
