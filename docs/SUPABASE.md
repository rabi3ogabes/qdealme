# Running QDealMe on Supabase

QDealMe uses PostgreSQL, so Supabase works as the production database without code changes. Laravel connects directly to the Supabase Postgres database. The Supabase client libraries and API keys are not used.

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

Flyer images are stored on the server's `public` disk (`storage/app/public`). For multiple servers or serverless hosting, switch the `public` disk to Supabase Storage via its S3-compatible endpoint, which requires the `league/flysystem-aws-s3-v3` package.

## Tests

Run the test suite against a local PostgreSQL database (`qdealme_test`), never against Supabase. The tests wipe the database.
