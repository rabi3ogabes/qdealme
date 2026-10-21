# QDealMe · كيو ديل مي

**Smart deals. Zero noise. · عروض ذكية. بلا ضجيج.**

QDealMe (qdealme.com) is an Arabic-first, bilingual web app where shoppers in Qatar browse every current supermarket, hypermarket and electronics flyer, and find verified deals, without pop-ups or ad floods. Stores publish their flyers through a secure admin panel.

See [docs/BRAND.md](docs/BRAND.md) for the slogan shortlist and visual identity.

## What is included

**For shoppers**
- Arabic (right-to-left) and English, with matching URLs: `/{ar|en}/qatar/{city}/…`
- Light, dark and automatic themes, and a choice of 123 or ١٢٣ digits
- City home with quick filters, a store logo strip, Featured / Latest / Ending soon sorting, and infinite scroll
- An ad-free flyer viewer: swipe, keyboard arrows, pinch and double-tap zoom, thumbnails, `?page=N` deep links, RTL-aware page direction, tappable verified-price hotspots, share sheet, report a problem
- Store pages with current, upcoming and past flyers, verified deals, branches and opening hours
- Verified deals catalogue with category tree, store, price and sort filters, and product pages with unit prices
- Search that understands Arabic spelling variants (أ/ا/إ, ة/ه, diacritics) and Arabic–English synonyms (ايفون ↔ iPhone), plus a ⌘K / Ctrl+K command palette
- Accounts: save flyers, follow stores, profile and language settings, delete-my-account
- About, Contact, Privacy, Terms and "List your store" (creates a lead in the admin)
- SEO: unique bilingual titles and descriptions, canonical and hreflang tags, Open Graph images, JSON-LD (Organization, WebSite + SearchAction, BreadcrumbList, ItemList, Product/Offer, Store), sitemaps, and `noindex` + HTTP 410 for expired content

**For the team (admin at `/admin`)**
- Filament admin in Arabic or English per staff member, with authenticator-app two-factor login and recovery codes
- Stores and branches, flyer sources (permission records with approve / revoke), flyers, offers, categories, cities, leads, messages and reports
- Flyer upload: PDF or images → background job rasterises pages and builds WebP/JPEG sizes → review → approve. Flyers publish and expire automatically at Qatar midnight.
- Offer review queue with one-click and bulk verification (with sanity rules), and a dashboard of what needs attention

**Trust and safety**
- Flyers can only come from approved sources, enforced in the form, the model, the publish action and a PostgreSQL trigger ([ADR 002](docs/decisions/002-lawful-sourcing.md))
- Staff can pull a store's current flyer from the official link on its own website into a draft for review ([docs/RETAILER-FEEDS.md](docs/RETAILER-FEEDS.md))
- Staff can import a flyer that a store posted on its own Facebook or Instagram page: paste the post link, drop in the images, and the store and offer dates are filled in automatically (same doc)
- Each store can get a private upload link: whatever its own team uploads there arrives as a draft, with no work for staff (same doc)
- With Meta's approval, new flyer posts on a store's official Facebook page or Instagram account are imported as drafts automatically every day, through Meta's official Graph API (same doc)
- Only verified prices appear in deals, search and product pages
- Rate-limited forms with a honeypot, security headers, no third-party trackers, staff avatars rendered locally

## Requirements

- PHP 8.3+ with `pdo_pgsql`, `gd` (WebP), `intl`, `mbstring`, `fileinfo`
- Composer 2, Node.js 20+ and npm
- PostgreSQL 14+ (16 recommended)
- `pdftoppm` from Poppler for PDF flyers (`apt install poppler-utils`, `brew install poppler`)

## Local setup

New to Laravel? Follow the step-by-step guide for Windows and Mac: [docs/RUN-LOCALLY.md](docs/RUN-LOCALLY.md).

```bash
git clone https://github.com/rabi3ogabes/qdealme.git && cd qdealme
composer install
npm install
cp .env.example .env            # then set DB_* and ADMIN_PASSWORD
createdb qdealme                # or create the database in your PostgreSQL tool
composer setup                  # key, migrate + seed (demo data), storage link
npm run build
composer dev                    # web server, queue worker, logs and Vite
```

Open http://localhost:8000. You are redirected to `/ar/qatar/doha` (or `/en/...` if your browser prefers English).

The admin panel is at http://localhost:8000/admin. Log in with `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`; if `ADMIN_PASSWORD` was empty, the seeder prints a generated one. You will be asked to set up an authenticator app on first login (set `ADMIN_REQUIRE_MFA=false` only on your own machine).

Seeding creates ten **fictional** demo stores with generated flyers, so you can try everything immediately. It takes about two minutes because every page image is processed for real. In production, demo data is skipped unless `SEED_DEMO=true`.

## Running in production

Use Supabase (or any managed PostgreSQL) as the database: see [docs/SUPABASE.md](docs/SUPABASE.md). To host on Vercel instead of a PHP server, follow [docs/VERCEL.md](docs/VERCEL.md).

1. Point your web server's document root at `public/` and run `php artisan storage:link`.
2. Keep a queue worker running (for example with Supervisor): `php artisan queue:work --tries=2 --timeout=900`
3. Add the scheduler to cron, which publishes and expires flyers every 15 minutes:
   `* * * * * cd /path/to/qdealme && php artisan schedule:run >> /dev/null 2>&1`
4. Raise PHP upload limits for big PDFs: `upload_max_filesize = 100M`, `post_max_size = 100M`. The app limit is `QDEALME_MAX_UPLOAD_MB` (default 80).
5. Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, and run `php artisan optimize`.

## Tests

The suite runs against a PostgreSQL database named `qdealme_test` (see `phpunit.xml`):

```bash
createdb qdealme_test
php artisan test
vendor/bin/pint --test
```

It covers flyer scheduling and expiry across Qatar midnight, licence enforcement (including the database trigger), PDF and image processing, the admin create → process → approve workflow, verification rules, search normalisation, SEO tags, both languages, forms, accounts and admin permissions. GitHub Actions runs the same checks on every push (`.github/workflows/ci.yml`).

## Project structure

| Path | What it holds |
|---|---|
| `app/Models`, `app/Enums` | Domain models and statuses |
| `app/Actions/Flyers` | Publish, expire, roll statuses, revoke source |
| `app/Jobs`, `app/Services` | Upload processing, listing queries |
| `app/Http/Controllers`, `routes/web.php` | Public site |
| `app/Filament` | Admin panel |
| `resources/views`, `resources/js`, `resources/css` | Blade views, Alpine components, design tokens |
| `lang/ar`, `lang/en` | All interface text |
| `database/seeders` | Locations, categories, admin user, fictional demo content |
| `docs/` | Brand guide and architecture decisions |

## Not built yet

These parts of the full roadmap are not in this version: a public JSON API for mobile apps, Meilisearch (search uses PostgreSQL today), automatic product extraction with AI, cross-store product matching and price history, shopping lists, price alerts and push notifications, an installable PWA, social sign-in, a self-serve retailer portal with analytics, paid plans, and coupons.
