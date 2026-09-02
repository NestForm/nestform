=== Nestform ===
Contributors: nestform
Tags: forms, contact form, survey, quiz, lead generation
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build forms, quizzes and surveys for WordPress that convert — entries inbox, email, spam protection, and analytics.

== Description ==

Nestform is a focused form builder for lead capture, feedback, and interactive flows — not a Contact Form 7 clone.

**Free** ($0):

* Unlimited forms with templates
* Drag-and-drop builder, conditional field visibility, file uploads
* Entries inbox, CSV export, plain-text email notifications
* Outbound webhooks (up to 5 HTTPS endpoints per form)
* reCAPTCHA, honeypot, rate limiting
* Gutenberg block, shortcode, basic analytics

**Pro** ($9.99/mo or $89.99/yr — single site):

* Multi-step flows, branch rules
* Quizzes & surveys with scoring and result bands
* HTML email designer, PDF attachments, automations
* Native integrations (Telegram, Slack, Google Sheets — roadmap)
* Advanced fields (rating, signature, NPS, scale, ranking, matrix)
* Calculated fields, repeaters, advanced analytics, lead insights

**Agency** ($29.99/mo or $269.99/yr — up to 5 client sites):

* Everything in Pro
* Priority support & onboarding
* Agency license for client sites
* White-label ready workflows and early access to integrations

Nestform Pro is a **separate add-on** (`nestform-pro`) sold on [nestform.app](https://nestform.app/pro) and hosted outside the WordPress.org directory. Premium code is not included in this free download. Compare plans in **Forms → Pro** or on [nestform.app/docs](https://nestform.app/docs).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/nestform/` or install from the WordPress plugins screen.
2. Activate **Nestform** through the **Plugins** menu.
3. Open **Forms** in the admin menu to create your first form.
4. Embed with the Gutenberg block or shortcode `[nestform id="123"]`.
5. Documentation lives on [nestform.app/docs](https://nestform.app/docs). Use **Forms → Developers** for hooks and filters.

For Pro features, purchase on nestform.app, install the `nestform-pro` add-on, and enter your license key under **Forms → License**.

== Frequently Asked Questions ==

= Is Nestform Pro included in this download? =

No. This is the free plugin. Nestform Pro is a separate premium add-on purchased on nestform.app and installed as its own plugin.

= How do I activate Pro after purchase? =

Install **Nestform Pro**, open **Forms → License**, and paste the license key from your purchase email.

= How many forms can I create on the free plan? =

Unlimited. Pro adds advanced builder features (multi-step, quizzes, native integrations, and more), not more form slots.

= What is the difference between Pro and Agency? =

Pro unlocks all premium features on one WordPress site. Agency includes everything in Pro plus a multi-site license (up to 5 client sites), priority support, and team-oriented perks. See **Forms → Pro** or [nestform.app/docs](https://nestform.app/docs) for the full comparison.

= Does Nestform store submissions? =

Yes. Submissions appear under **Forms → Entries** with export to CSV.

= Does Nestform add branding to my public site? =

No. Nestform does not inject “powered by” links or credits on your front-end forms unless you add them yourself.

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

* Used for: purchasing Nestform Pro and managing your subscription.
* When: only if you choose to buy Pro from our website.
* The free plugin does not require a nestform.app account.

== Changelog ==

= 2.2.0 =
* Free plugin: unlimited forms, no Freemius SDK; Pro sold on nestform.app.
* Pro add-on: site license key activation.
* Admin preview submits no longer create entries.
* In-app **Pro** page and **Developers** reference.
* WordPress.org compliance: premium runtime ships in Nestform Pro add-on only.

== Upgrade Notice ==

= 2.2.0 =
Free plugin with unlimited forms. Nestform Pro is a separate add-on purchased on nestform.app.
