# Nestform

Build **forms, quizzes and surveys** for WordPress that convert.

Not a Contact Form 7 clone — a focused product around:

**Forms · Quizzes · Surveys · Leads · Analytics**

**Path:** `wp-content/plugins/nestform/`  
**Main file:** `nestform.php`  
**Pro add-on:** `wp-content/plugins/nestform-pro/` (sold separately)

## Positioning

| Pillar | What users get |
|--------|----------------|
| **Lead forms** | Contact / quote / callback → Entries → Email → (Pro) Webhook / PDF |
| **Interactive forms** | Multi-step quizzes with scoring, bands, branching, shareable results |
| **Survey / feedback** | NPS, matrix, ranking + insights |

Free = strong lead capture. Pro = interactive conversion flows.

## Free vs Pro

| Free | Pro (Nestform Pro + valid license) |
|------|-------------------------------------|
| Up to 5 forms (hard limit) | Unlimited forms |
| Basic/layout fields, file uploads, conditionals | Multi-step + branch rules |
| Entries, CSV, captcha, honeypot, mail | Webhooks |
| Templates, appearance, JSON import/export | Quiz & survey (scoring, bands, timer, attempts, share) |
| Basic analytics | Advanced analytics / lead insights / charts |
| — | Advanced fields (rating, signature, NPS, scale, ranking, matrix) |
| — | Calculated fields, repeaters |
| — | HTML email designer + PDF |

Capabilities are registered only by Nestform Pro via `Nestform_Features::register()` after a valid **Freemius** license. Filtering `nestform_is_pro` alone does not unlock gated runtime.

## Pricing (Freemius)

| Plan | Monthly | Annual |
|------|---------|--------|
| **Pro** (1 site) | $9.99 | $89.99 |
| **Agency** (5 sites) | $29.99 | $269.99 |

Checkout and licensing: see [FREEMIUS.md](FREEMIUS.md).

## Features

- Multiple forms as CPT `nestform` (fields, messages, mail, settings in post meta)
- Admin builder: DnD, quick-add, **Undo**, **Preview** drawer, templates
- **Duplicate form** + starter templates
- Layout blocks: Heading, Image, HTML
- File upload: extensions, max MB, **max files** (multi) + entry previews
- Conditional show/hide fields
- Mail: CC/BCC, autoreply, **extra conditional notification**
- Time-trap spam + optional Akismet
- Entries: CSV export, status (New / Read / Spam)
- Captcha via **Forms → Integrations** (reCAPTCHA v2/v3)
- Plugin Settings: email defaults, entry date format, uninstall cleanup
- Form JSON import / export (local → production)
- Message packs EN/RU, a11y on steps/errors
- Hooks: `nestform_loaded`, `nestform_submitted`, `nestform_mail_sent`, `nestform_webhook_payload`, …

## Roadmap (product)

1. Builder UX polish  
2. Quiz/Survey result screens + analytics  
3. Automations (WHEN / IF / THEN)  
4. Integrations (Sheets, Telegram, Slack, HubSpot…)  
5. Pricing tiers (Free / Pro / Agency)

## Validation (built-in)

| Check | Where |
|-------|--------|
| Required | server + light HTML5 hints |
| Email / phone / URL / number / date / time | server (+ front hints) |
| Select must match options | server |
| Length caps (text 500 / textarea 10k) | server |
| Nonce + honeypot + rate limit | server |
| Time trap | server (silent success) |
| Akismet (optional) | server |
| File (type / size / count) | server + client |
| Hidden by condition / skipped steps | skipped on server + front |

## Multi-step & branching (Pro)

1. Activate Nestform Pro and license.
2. Fields tab → enable multi-step, name steps, assign fields.
3. Branch rules: `from|field|op|value|to`
