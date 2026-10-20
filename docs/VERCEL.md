# Deploying QDealMe on Vercel

QDealMe is a Laravel (PHP) app. This repository includes the configuration to run it on Vercel with the community PHP runtime (`vercel-php`): `vercel.json`, `api/index.php` and `api/php.ini`.

A regular PHP server (see the README's production section) has fewer limits. Choose Vercel if you prefer its hosting and previews.

## What works differently on Vercel

| Feature | On a PHP server | On Vercel |
|---|---|---|
| Shopper website, search, accounts, admin | Yes | Yes |
| Flyer uploads as JPG, PNG or WebP | Yes, with resized copies | Yes, stored as uploaded |
| Flyer uploads as PDF | Yes | No: Vercel has no PDF tool, so the admin only offers images |
| Automatic publish and expiry | Every 15 minutes | Once a day at 00:05 Qatar time (Vercel Cron) |
| Fictional demo stores (`DemoContentSeeder`) | Yes | Seed them from your computer (step 5) |

## 1. A database

The app needs PostgreSQL that Vercel can reach. Either works:

- **Neon (easiest):** in your Vercel project, open **Storage → Create Database → Neon**. Vercel adds `POSTGRES_URL` automatically.
- **Supabase:** follow [docs/SUPABASE.md](SUPABASE.md), then add `DB_URL` (session pooler URI) in Vercel.

`vercel.json` runs the app in Mumbai (`"regions": ["bom1"]`), next to a Supabase project in South Asia (Mumbai). If your database is in another region, change `regions` to the Vercel region closest to it. Every page makes several database queries, so a far-away database makes the site slow.

Add every variable below for both **Production** and **Preview**. Pull request builds are Preview builds and cannot see Production-only variables.

## 2. Cloud file storage

Vercel forgets files between requests, so flyer images and logos must live in S3-compatible storage. **Cloudflare R2** is a good fit (free tier, no download fees):

1. Create two buckets, for example `qdealme-media` (public) and `qdealme-private` (private).
2. Make `qdealme-media` public: connect a custom domain such as `media.qdealme.com`, or enable its `r2.dev` URL.
3. Create an R2 API token with **Object Read & Write** on both buckets.
4. On **`qdealme-private`**, add this CORS rule so the admin can upload straight from the browser. Replace the origin with your site's address.

```json
[{ "AllowedOrigins": ["https://qdealme.com"], "AllowedMethods": ["PUT", "GET"], "AllowedHeaders": ["*"], "MaxAgeSeconds": 3000 }]
```

If your database is on Supabase, you can use **Supabase Storage** instead of R2. The values are listed in [docs/SUPABASE.md](SUPABASE.md#files). Use them in place of the R2 values below.

## 3. Environment variables in Vercel

In **Project → Settings → Environment Variables**, add:

| Name | Value |
|---|---|
| `APP_KEY` | Run `php artisan key:generate --show` on your computer and paste the result |
| `APP_URL` | `https://your-domain` (or the `.vercel.app` address) |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Your first admin login (created during the build) |
| `ADMIN_REQUIRE_MFA` | `true` |
| `MEDIA_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` |
| `MEDIA_ACCESS_KEY_ID` / `MEDIA_SECRET_ACCESS_KEY` | From the R2 API token |
| `MEDIA_PUBLIC_BUCKET` / `MEDIA_PRIVATE_BUCKET` | `qdealme-media` / `qdealme-private` |
| `MEDIA_PUBLIC_URL` | `https://media.qdealme.com` (the public bucket's address) |
| `CRON_SECRET` | Any long random text; Vercel Cron sends it automatically |
| `DB_URL` | Only for Supabase; Neon sets `POSTGRES_URL` itself |

`api/index.php` sets the remaining serverless settings. These cover caches in `/tmp`, logs to Vercel, sessions and cache in the database, `MEDIA_STORAGE=cloud` and `QDEALME_IMAGE_PROCESSING=basic`. You don't need to add them.

## 4. Project settings

In **Project → Settings → Build and Deployment**:

- **Framework Preset:** Other.
- **Build Command, Output Directory and Install Command:** leave overrides off; `vercel.json` sets them.

Then redeploy. Each build installs PHP dependencies, removes unused parts of the AWS library to stay under Vercel's size limit, runs database migrations, and seeds cities, categories and the admin account.

## 5. Optional: demo content

Demo flyers are drawn with the GD image library, which Vercel lacks. To fill the live site with the fictional demo stores, run this once from your computer (see [RUN-LOCALLY.md](RUN-LOCALLY.md)), using the same database and storage values as Vercel in your local `.env`, plus `MEDIA_STORAGE=cloud`:

```bash
php artisan db:seed --class=DemoContentSeeder --force
```

## Updating

The Filament admin panel's files in `public/css/filament`, `public/js/filament` and `public/fonts/filament` are committed, because Vercel serves them as static files. After upgrading Filament, run `php artisan filament:assets` and commit the changes.
