# Pulling flyers from a store's official link

Staff can pull a store's current flyer straight from the store's own website instead of uploading it by hand. The pull always creates a **draft**: nothing is published until a reviewer approves it.

This only works for stores that agreed to it, and only from the store's own website. QDealMe never copies flyers from other flyer sites (see [ADR 002](decisions/002-lawful-sourcing.md)).

## Setting up a store

1. **Stores:** open the store and fill in **Website** with its official address, for example `https://www.carrefourqatar.com`.
2. **Get permission:** ask the store's marketing team for written permission to show their flyers, and for the link where they publish the current one. Keep the email or letter.
3. **Flyer sources → New:**
   - **Store:** the store
   - **Type:** Retailer feed
   - **Official flyer link:** the link from the store
   - **Licence document:** the permission email or letter
4. A super admin or content admin clicks **Approve licence**.

## Pulling the flyer

Click **Pull latest flyer** on the store's row in **Stores**, or on the source's row in **Flyer sources**.

- If the flyer is new, QDealMe downloads it, creates a draft and opens it. Check the title, dates, cities and pages, then approve it as usual.
- If the store hasn't changed its flyer since the last pull, nothing is created.
- If something is wrong (the link moved, or the file is too big), the reason is shown and saved under **Last pulled** on the source.

### Many stores at once

- **Dashboard → Pull all store feeds** pulls every approved feed and shows a summary: new drafts, unchanged, and failed with the reason for each store.
- **Stores** or **Flyer sources**: tick several rows, then **Bulk actions → Pull latest flyers**.
- **Automatically, every day:** the scheduler runs `php artisan flyers:pull-feeds` at 06:00 Qatar time. On Vercel, the cron job calls `/cron/feeds/pull` at 06:00 Qatar time, protected by `CRON_SECRET`. New flyers wait in the dashboard's **Review queue** for approval.

One store failing never stops the others.

## Importing from a store's Facebook or Instagram post

Many stores, such as Paris Hypermarket, post their flyer on their own Facebook or Instagram page instead of their website. QDealMe does not download from social networks (their terms forbid it, and they block it), so staff add the images and QDealMe does the rest.

**Set up once per store:**

1. Get the store's written permission to show the flyers it posts.
2. **Flyer sources → New:** Store; **Type:** Store's social media posts; **Official page link:** the store's page, for example `https://www.facebook.com/ParisHypermarketQatar`; the permission as the licence document.
3. A super admin or content admin approves the licence.

**Import a post:** **Dashboard** (or **Flyers**) **→ Import from a store post**.

1. Paste the post link. A link that names the page (`facebook.com/ParisHypermarketQatar/posts/…`) selects the store and its source automatically. A share link (`facebook.com/share/p/…`) does not name the page, so pick the store yourself. The ↗ button opens the post in a new tab.
2. Save each flyer image from the post (or copy it), then drop or paste them into **Flyer images from the post**. Drag them into page order.
3. Optional: paste the post text. The offer dates are read from it in Arabic or English, for example `20 - 26 October`, `من ٢٠ إلى ٢٦ أكتوبر`, `22/10 - 28/10` or `valid until 30 Oct`. Otherwise the flyer runs for 7 days from today.
4. **Create draft.** The draft opens, keeps the post link under **Links**, and waits for review like any other flyer.

The same post link (tracking parameters such as `fbclid` are ignored) or the same set of images cannot be imported twice. QDealMe shows the existing flyer instead.

## What the link may return

The link must use `https://` and be on the store's website domain, or a subdomain of it such as `cdn.carrefourqatar.com`. Redirects are not followed, and links to private network addresses are refused.

**1. The flyer itself:** a single JPG, PNG or WebP image, or a PDF. PDFs need Poppler, so they are not available on Vercel. A single file becomes a one-page draft valid for 7 days from today.

**2. A flyer manifest** (recommended): a small JSON file the store publishes, listing the pages in order and the dates:

```json
{
  "title_en": "Weekly Super Deals",
  "title_ar": "عروض الأسبوع",
  "valid_from": "2026-10-22",
  "valid_until": "2026-10-28",
  "pages": [
    "https://www.carrefourqatar.com/flyers/2026-43/page-1.jpg",
    "https://www.carrefourqatar.com/flyers/2026-43/page-2.jpg"
  ]
}
```

Only `pages` is required. Page images may be on any public `https://` host the store chooses, such as its image CDN, because the manifest on its own website vouches for them. The limits are 40 pages, and `QDEALME_MAX_UPLOAD_MB` per file (80 MB by default).

## Store list

`RetailerDirectorySeeder` adds the main Qatar chains (Carrefour, LuLu, Monoprix, Al Meera, Safari, Ansar Gallery, Jarir and others) with Arabic and English names. They start **hidden**. Add the logo the store gives you and switch the store on once its first flyer is approved. The seeder runs on every Vercel build and in `composer setup`, but it never changes a store that already exists.
