# 002. Lawful content sourcing is enforced in three layers

**Status:** accepted

Flyers may only come from retailers who gave permission. Every flyer must point at a `flyer_sources` record with `license_status = approved`. This is enforced:

1. **In the admin form**: only approved sources for the chosen store can be selected, and a validation rule re-checks it.
2. **In the model and actions**: `Flyer` refuses to save with a non-approved source, and `PublishFlyer` re-checks before publishing.
3. **In the database**: a PostgreSQL trigger rejects inserts or updates that reference a non-approved source, even from raw SQL.

Revoking a source (`RevokeFlyerSource`) archives all of its flyers immediately. There is no scraper of third-party flyer sites in the codebase, and a test (`LicenceEnforcementTest::test_no_scraper_code_exists`) guards that.

The demo stores in `DemoContentSeeder` are fictional and their flyers are generated images, so even demo data contains no third-party content.
