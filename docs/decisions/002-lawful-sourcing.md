# 002. Lawful content sourcing is enforced in three layers

**Status:** accepted

Flyers may only come from retailers who gave permission. Every flyer must point at a `flyer_sources` record with `license_status = approved`. This is enforced:

1. **In the admin form**: only approved sources for the chosen store can be selected, and a validation rule re-checks it.
2. **In the model and actions**: `Flyer` refuses to save with a non-approved source, and `PublishFlyer` re-checks before publishing.
3. **In the database**: a PostgreSQL trigger rejects inserts or updates that reference a non-approved source, even from raw SQL.

Revoking a source (`RevokeFlyerSource`) archives all of its flyers immediately.

A **retailer feed** source can hold the official link where the store publishes its current flyer. Staff can then pull the flyer into a draft (`PullLatestFlyer`, see [RETAILER-FEEDS.md](../RETAILER-FEEDS.md)). The link must be on the store's own website domain (`RetailerFeedClient` checks it against the store's `website_url`), so a pull can only read the store's own publication, never another flyer site. The source still has to be approved, and the result is a draft that a reviewer must approve. There is no scraper of third-party flyer sites in the codebase, and a test (`LicenceEnforcementTest::test_no_scraper_code_exists`) guards that.

Many Qatar stores publish their weekly flyer as a post on their own Facebook or Instagram page. A **social media** source records the store's permission and its official page. Staff then import a post with **Import from a store post** (`ImportFlyerFromPost`): they paste the post link and add the post's images themselves. QDealMe never downloads from social networks, because their terms forbid automated collection. Only the store's own posts may be imported, the source must be approved, the result is a draft for review, and the same post or the same images cannot be imported twice.

Two routes need no manual work from staff, and both stay within these rules:

- **Store upload link.** Each approved source can have a private link (`/store/upload/{token}`). The store's own team uploads its flyer there, and it arrives as a draft. The link is a long random token tied to the approved source. It stops working when the licence is revoked or staff issue a new link.
- **Meta's official Graph API.** `PullSocialPosts` reads a store's official Facebook page or Instagram account through the API, never by loading facebook.com or instagram.com. Reading another business's page requires Meta's approval of QDealMe's app: Page Public Content Access for Facebook, and Advanced Access for Instagram business discovery. It still needs the store's approved permission, and it creates drafts for review.

The demo stores in `DemoContentSeeder` are fictional and their flyers are generated images, so even demo data contains no third-party content.
