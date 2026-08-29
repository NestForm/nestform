# Freemius release checklist

Nestform uses [Freemius](https://freemius.com) as merchant of record. The **free** plugin (`nestform`) hosts the SDK; **Nestform Pro** (`nestform-pro`) is the premium add-on.

## 1. Create product in Freemius

1. [Freemius Dashboard](https://dashboard.freemius.com) → **Add Product** → WordPress Plugin.
2. Name: **Nestform**, slug: `nestform`.
3. Enable **Premium version** / add-on, premium slug: `nestform-pro`, suffix: `Pro`.
4. Plans (suggested):

| Plan | Sites | Monthly | Annual |
|------|-------|---------|--------|
| **Pro** | 1 | $9.99 | $89.99 |
| **Agency** | 5 | $29.99 | $269.99 |

5. Copy **Product ID**, **Public key**, and each **Pricing ID** from the Plans screen.

## 2. SDK integration

Snippet lives in **`nestform.php`** as `nes_fs()` (Freemius-generated). SDK path: `vendor/freemius/start.php`.

Optional: set plan **Pricing IDs** in `freemius.config.php` for direct checkout URLs from the Upgrade page.

## 3. Distribution

- **WordPress.org**: ship `nestform` only (free). Do **not** include `nestform-pro` in the org zip.
- **Freemius**: upload `nestform-pro` as the premium download; free plugin zip includes `vendor/freemius/` SDK.
- After purchase, customers install **Nestform Pro** from their Freemius account or automatic premium install if enabled.

## 4. License flow

- Checkout → Freemius activates license on site.
- `nestform_fs()->can_use_premium_code()` unlocks Pro features.
- **Forms → Account** opens Freemius account (license, billing, downloads).
- Custom dev license (`NESTFORM_PRO_DEV_LICENSE`) is **removed** — use Freemius sandbox + test cards instead.

## 5. Pre-launch QA

- [ ] Free plugin works with Pro **not** installed.
- [ ] Upgrade page shows $9.99 / $89.99 and $29.99 / $269.99; billing toggle updates checkout URLs.
- [ ] Sandbox purchase unlocks multi-step, webhooks, etc.
- [ ] Preview submit does not create entries (already gated).
- [ ] Deactivate / expire license → Pro features lock, data preserved.

## 6. Updating the SDK

```bash
cd wp-content/plugins/nestform
rm -rf freemius
git clone --depth 1 https://github.com/Freemius/wordpress-sdk.git freemius
rm -rf freemius/.git
```

Test in staging after each SDK bump.
