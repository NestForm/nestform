=== Nestform ===
Contributors: nestform
Tags: forms, contact form, survey, quiz, lead generation
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build forms, quizzes and surveys for WordPress that convert — entries inbox, email, spam protection, and analytics.

== Description ==

Nestform is a focused form builder for lead capture, feedback, and interactive flows — not a Contact Form 7 clone.

**Builder**

* Unlimited forms, starter templates (open on Add New when the canvas is empty)
* Drag-and-drop fields, undo, live preview
* Conditional show/hide, file uploads, layout blocks (heading, image, HTML)
* Appearance skins and per-form styling
* Duplicate forms, JSON import/export, import from Contact Form 7 and WPForms

**Inbox & mail**

* Entries with New / Read / Spam, star, CSV export, printable entry view
* Response summary: totals, fill rate, most chosen / most skipped answers
* Plain-text notifications, CC/BCC, optional autoreply
* Outbound webhooks (HTTPS endpoints you configure per form)

**Spam & embed**

* Honeypot, time trap, rate limit, optional Akismet
* Google reCAPTCHA v2/v3 via **Forms → Integrations**
* Gutenberg block and shortcode `[nestform id="123"]`

**Admin**

* Dashboard with submission charts
* Light / dark admin theme
* **Developers** screen with hooks and filters

**Optional Nestform Pro** is a **separate add-on** (`nestform-pro`), sold on [nestform.app](https://nestform.app) and hosted outside the WordPress.org directory. Premium code is not included in this download. It unlocks multi-step flows, quizzes and surveys, advanced fields, HTML email, PDF attachments, automations, and richer analytics. Compare features under **Forms → Pro**.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/nestform/` or install from the WordPress plugins screen.
2. Activate **Nestform** through the **Plugins** menu.
3. Open **Forms** in the admin menu to create your first form.
4. Embed with the Gutenberg block or shortcode `[nestform id="123"]`.
5. Documentation: [nestform.app/docs](https://nestform.app/docs). Hooks live under **Forms → Developers**.
6. Source and build tools: [github.com/NestForm/nestform-free](https://github.com/NestForm/nestform-free). From the plugin root run `npm run build`, then `python bin/build-release.py`.

For Pro features, install the `nestform-pro` add-on from nestform.app and enter your license key under **Forms → License**.

== Source ==

Human-readable PHP ships in this plugin. CSS and JS are built from sources in the same GitHub repository:

https://github.com/NestForm/nestform-free

```
npm run build
python bin/build-release.py
```

The WordPress.org zip contains compiled `*.min.js` and bundled CSS. Edit the sources in that repo, then rebuild.

== Frequently Asked Questions ==

= Is Nestform Pro included in this download? =

No. This is the free plugin. Nestform Pro is a separate add-on purchased on nestform.app and installed as its own plugin.

= How do I activate Pro after purchase? =

Install **Nestform Pro**, open **Forms → License**, and paste the license key from your purchase email.

= How many forms can I create? =

Unlimited on the free plugin. Pro adds builder features (multi-step, quizzes, advanced fields, and more), not extra form slots.

= Does Nestform store submissions? =

Yes. Submissions appear under **Forms → Entries**. You can export CSV and print a single entry.

= Does Nestform add branding to my public site? =

No. Nestform does not inject “powered by” links or credits on front-end forms unless you add them yourself.

== External services ==

This plugin can connect to optional third-party services configured by the site administrator:

**Outbound webhooks** (optional)

* Used for: POST JSON to HTTPS endpoints you configure per form (Settings → Webhooks).
* When: after each successful submission, if webhooks are enabled for that form.
* Data sent: form fields, entry metadata, and site URL — only to URLs you enter.

**Google reCAPTCHA** (optional)

* Used for: spam protection on forms.
* When: after you save site and secret keys under **Forms → Integrations**.
* Data sent: challenge response tokens and related anti-spam data per [Google's policies](https://policies.google.com/privacy).
* Terms: https://policies.google.com/terms

**nestform.app** (optional — Pro purchase only)

* Used for: purchasing Nestform Pro and managing your license.
* When: only if you choose to buy Pro from our website.
* The free plugin does not require a nestform.app account.

== Bundled fonts ==

Admin UI uses self-hosted **Plus Jakarta Sans** and **Sora** (SIL Open Font License 1.1). Font files ship under `assets/fonts/` with `assets/fonts/OFL.txt`. No Google Fonts CDN is used.

== Bundled flags ==

The phone country picker uses self-hosted SVG flags from [flag-icons](https://github.com/lipis/flag-icons) (MIT). Files ship under `assets/flags/` with `assets/flags/LICENSE.txt`. No flag CDN is used.

== Changelog ==

= 2.2.1 =
* Printable entries: two-column label/value layout so answers line up.
* Empty Add New forms open the templates gallery; starter cards stay on the canvas.
* Response summary: KPIs, most chosen / most skipped, richer field cards.
* Unread counts use a ripple; New status uses a quiet border pulse.
* Phone country flags are bundled as local SVGs (no flagcdn.com).

= 2.2.0 =
* Free plugin: unlimited forms, no Freemius SDK; Pro sold on nestform.app.
* Pro add-on: site license key activation.
* Admin preview submits no longer create entries.
* In-app **Pro** page and **Developers** reference.
* WordPress.org compliance: premium runtime ships in Nestform Pro add-on only.

== Upgrade Notice ==

= 2.2.1 =
Print layout, templates on empty forms, and a clearer response summary.

= 2.2.0 =
Free plugin with unlimited forms. Nestform Pro is a separate add-on purchased on nestform.app.
