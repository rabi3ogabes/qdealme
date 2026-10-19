# 001. Stack: Laravel, Blade with Alpine.js, Filament, PostgreSQL

**Status:** accepted

## Context

The build brief proposed Inertia + React + SSR. The site is mostly content pages that must be fast, crawlable and right-to-left perfect, with a few rich interactions (flyer viewer, search palette, infinite scroll).

## Decision

- **Laravel 13** with server-rendered **Blade** views and **Alpine.js** for interactivity. Every page is fully rendered HTML on first byte, so SEO needs no separate SSR server, and the total JavaScript is about 31 KB gzipped.
- **Tailwind CSS v4** with a design-token layer (`resources/css/app.css`) and logical properties for RTL.
- **Filament 5** for the admin panel, with authenticator-app two-factor login.
- **PostgreSQL 16**. A database trigger enforces that flyers only use approved sources.
- **Poppler `pdftoppm`** rasterises PDFs; **Intervention Image** (GD) creates WebP and JPEG derivatives.

## Consequences

The public site is a single Laravel app that is easy to host on any PHP server. If a native mobile app is built later, a JSON API can reuse the same models and query services (`app/Services/FlyerListing.php`, the model scopes).
