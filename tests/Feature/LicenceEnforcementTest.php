<?php

namespace Tests\Feature;

use App\Actions\Flyers\PublishFlyer;
use App\Actions\Flyers\RevokeFlyerSource;
use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Exceptions\UnlicensedSourceException;
use App\Models\Flyer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LicenceEnforcementTest extends TestCase
{
    private function attributes(int $retailerId, int $sourceId): array
    {
        return [
            'retailer_id' => $retailerId,
            'flyer_source_id' => $sourceId,
            'title_en' => 'Deals',
            'title_ar' => 'عروض',
            'slug' => 'deals',
            'valid_from' => today(),
            'valid_until' => today()->addWeek(),
            'status' => 'draft',
        ];
    }

    public function test_model_refuses_pending_source(): void
    {
        $retailer = $this->makeRetailer();
        $source = $this->makeSource($retailer, LicenseStatus::Pending);

        $this->expectException(UnlicensedSourceException::class);

        Flyer::query()->create($this->attributes($retailer->id, $source->id));
    }

    public function test_model_refuses_revoked_source(): void
    {
        $retailer = $this->makeRetailer();
        $source = $this->makeSource($retailer, LicenseStatus::Revoked);

        $this->expectException(UnlicensedSourceException::class);

        Flyer::query()->create($this->attributes($retailer->id, $source->id));
    }

    public function test_database_trigger_refuses_unapproved_source_even_with_raw_sql(): void
    {
        $retailer = $this->makeRetailer();
        $source = $this->makeSource($retailer, LicenseStatus::Pending);

        $this->expectException(QueryException::class);

        DB::table('flyers')->insert($this->attributes($retailer->id, $source->id) + ['created_at' => now(), 'updated_at' => now()]);
    }

    public function test_publish_action_refuses_once_licence_is_revoked(): void
    {
        $flyer = $this->makeFlyer(['status' => FlyerStatus::InReview]);
        $flyer->source->forceFill(['license_status' => LicenseStatus::Revoked])->save();

        $this->expectException(UnlicensedSourceException::class);

        app(PublishFlyer::class)->handle($flyer->refresh());
    }

    public function test_revoking_a_source_takes_its_flyers_offline(): void
    {
        $flyer = $this->makeFlyer();

        $count = app(RevokeFlyerSource::class)->handle($flyer->source, 'Retailer withdrew permission');

        $this->assertSame(1, $count);
        $this->assertSame(FlyerStatus::Archived, $flyer->refresh()->status);
        $this->get($flyer->url())->assertNotFound();
    }

    public function test_no_scraper_code_exists(): void
    {
        $hits = collect(File::allFiles(app_path()))
            ->filter(fn ($f) => preg_match('/d4donline|Goutte|DomCrawler|symfony\/panther/i', $f->getContents()) === 1)
            ->map(fn ($f) => $f->getRelativePathname());

        $this->assertEmpty($hits->all(), 'Found scraper-like code: '.$hits->implode(', '));
    }
}
