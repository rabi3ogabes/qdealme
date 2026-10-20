# 002. Lawful content sourcing is enforced in three layers

**Status:** accepted

Flyers may only come from retailers who gave permission. Every flyer must point at a `flyer_sources` record with `license_status = approved`. This is enforced:

1. **In the admin form**: only approved sources for the chosen store can be selected, and a validation rule re-checks it.
2. **In the model and actions**: `Flyer` refuses to save with a non-approved source, and `PublishFlyer` re-checks before publishing.
3. **In the database**: a PostgreSQL trigger rejects inserts or updates that reference a non-approved source, even from raw SQL.

Revoking a source (`RevokeFlyerSource`) archives all of its flyers immediately.

A **retailer feed** source can hold the official link where the store publishes its current flyer. Staff can then pull the flyer into a draft (`PullLatestFlyer`, see [RETAILER-FEEDS.md](../RETAILER-FEEDS.md)). The link must be on the store's own website domain (`RetailerFeedClient` checks it against the store's `website_url`), so a pull can only read the store's own publication, never another flyer site. The source still has to be approved, and the result is a draft that a reviewer must approve. There is no scraper of third-party flyer sites in the codebase, and a test (`LicenceEnforcementTest::test_no_scraper_code_exists`) guards that.

The demo stores in `DemoContentSeeder` are fictional and their flyers are generated images, so even demo data contains no third-party content.
