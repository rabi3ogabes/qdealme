# Running QDealMe on Supabase

QDealMe uses PostgreSQL, so Supabase works as the production database without code changes. Laravel connects directly to the Supabase Postgres database. The Supabase client libraries and API keys are not used.

## Which Supabase values QDealMe needs

| Supabase value | Used by QDealMe? |
|---|---|
| `SUPABASE_URL`, `SUPABASE_PUBLISHABLE_KEY`, `SUPABASE_SECRET_KEY`, `SUPABASE_JWKS_URL` | **No.** These are for Supabase's JavaScript clients and Supabase Auth. Don't add them. |
| Database connection string (**Connect → Session pooler**) | **Yes**, as `DB_URL` (step 2) |
| Storage S3 access keys (**Storage → S3**) | Only if Supabase Storage holds the flyer images (see Files) |

Give QDealMe **its own Supabase project**. Never point it at a database another app uses. The migrations create common tables such as `users`, `sessions` and `cache`, which clash with other apps. They also turn on row-level security for every table in the `public` schema, which would block the other app's Supabase API access.

## 1. Create the project

1. In the Supabase dashboard, create a project named `qdealme`.
2. Pick **South Asia (Mumbai) `ap-south-1`**, the closest region to Qatar.
3. Choose a strong database password and keep it in your password manager.

On the free plan an account can have two active projects. Pause or delete an unused one, or upgrade, if Supabase refuses to create a third.

## 2. Connect Laravel

In the dashboard, open **Connect**, choose **Session pooler**, and copy the URI. Put your password into it and add it to `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_URL="postgresql://postgres.<project-ref>:<password>@aws-0-ap-south-1.pooler.supabase.com:5432/postgres"
DB_SSLMODE=require
```

Use the **session pooler** (port 5432 on the `pooler.supabase.com` host):

- The direct `db.<ref>.supabase.co` host is IPv6-only, which many servers cannot reach.
- The transaction pooler (port 6543) does not support the prepared statements Laravel uses.

## 3. Create the tables and data

```bash
php artisan migrate --force
php artisan db:seed --force                              # locations, categories, admin user
php artisan db:seed --class=DemoContentSeeder --force    # optional fictional demo stores
```

The seeder prints the admin password if `ADMIN_PASSWORD` is empty in `.env`.

## Security

Supabase publishes every table in the `public` schema through its REST and GraphQL APIs. The migration `2026_01_01_000080_enable_row_level_security` turns on row-level security for every table with no policies, so the public `anon` key cannot read or change anything. Laravel is unaffected because it connects as the tables' owner. The test `DatabaseSecurityTest` fails if a future migration adds a table without RLS. Enable RLS in any new migration that creates a table.

Never put the `service_role` key or the database password in front-end code. QDealMe only needs `DB_URL` on the server.

## Files

Flyer images are stored on the server's `public` disk (`storage/app/public`) by default. For several servers or for Vercel, set `MEDIA_STORAGE=cloud` and use any S3-compatible storage. Supabase Storage works:

1. In **Storage**, create a **public** bucket `qdealme-media` and a **private** bucket `qdealme-private`.
2. In **Storage → S3**, enable the S3 protocol and create an access key. The secret is shown only once.
3. Set these values. `<ref>` is your project ref, and the region is the project's region (for example `ap-south-1`).

```dotenv
MEDIA_STORAGE=cloud
MEDIA_ENDPOINT=https://<ref>.storage.supabase.co/storage/v1/s3
MEDIA_REGION=ap-south-1
MEDIA_PATH_STYLE=true
MEDIA_ACCESS_KEY_ID=<access key id>
MEDIA_SECRET_ACCESS_KEY=<secret access key>
MEDIA_PUBLIC_BUCKET=qdealme-media
MEDIA_PRIVATE_BUCKET=qdealme-private
MEDIA_PUBLIC_URL=https://<ref>.supabase.co/storage/v1/object/public/qdealme-media
```

Supabase Storage ignores S3 object ACLs. Whether a file is public depends on its bucket, so keep `qdealme-private` private. The S3 keys bypass all Storage policies, so keep them on the server only.

## Tests

Run the test suite against a local PostgreSQL database (`qdealme_test`), never against Supabase. The tests wipe the database.
