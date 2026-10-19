# QDealMe – notes for AI assistants

Laravel 13 + Blade + Alpine.js + Tailwind v4 + Filament 5 + PostgreSQL. Arabic-first (RTL) bilingual flyers and deals site for Qatar. Read `README.md` and `docs/decisions/` first.

- All UI text goes through `lang/ar/*.php` and `lang/en/*.php`; keep both files' keys in sync.
- Public routes live under `{locale}/{country}/{city}`; `App\Http\Middleware\SetLocale` resolves them and sets URL defaults. Use `city_route_params()` when building public URLs outside a request.
- Never add scrapers or third-party flyer content. Flyers must reference an approved `flyer_sources` row (enforced in model, action and a PostgreSQL trigger).
- Only `verified` offers may appear on deals, search and product pages.
- In Filament closures, name the record parameter `$record` (or type-hint the model) and the query parameter `$query`; Filament injects by name.
- Before committing: `vendor/bin/pint` and `php artisan test` (needs the `qdealme_test` PostgreSQL database).
