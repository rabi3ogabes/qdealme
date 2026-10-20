<?php

namespace Tests\Feature;

use App\Actions\Flyers\PullLatestFlyer;
use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Exceptions\FeedPullException;
use App\Filament\Resources\FlyerSources\Pages\ManageFlyerSources;
use App\Jobs\ProcessFlyerUpload;
use App\Models\City;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Models\Retailer;
use App\Services\RetailerFeedClient;
use Database\Seeders\RetailerDirectorySeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RetailerFeedTest extends TestCase
{
    private string $resolvedIp = '93.184.216.34';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Queue::fake();
        $this->app->instance(RetailerFeedClient::class, new RetailerFeedClient(fn () => [$this->resolvedIp]));
    }

    private function png(int $shade = 0): string
    {
        $im = imagecreatetruecolor(600, 900);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade, $shade, $shade));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function feedSource(string $feedUrl, ?string $website = 'https://www.carrefourqatar.com', LicenseStatus $status = LicenseStatus::Approved, SourceType $type = SourceType::RetailerFeed): FlyerSource
    {
        $retailer = $this->makeRetailer(['name_en' => 'Carrefour', 'name_ar' => 'كارفور', 'website_url' => $website]);

        return FlyerSource::query()->create([
            'retailer_id' => $retailer->id,
            'type' => $type,
            'license_status' => $status,
            'feed_url' => $feedUrl,
        ]);
    }

    private function pull(FlyerSource $source): ?Flyer
    {
        return app(PullLatestFlyer::class)->handle($source);
    }

    public function test_pulls_a_flyer_image_into_a_draft_for_review(): void
    {
        Http::fake(['https://www.carrefourqatar.com/flyers/latest.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $source = $this->feedSource('https://www.carrefourqatar.com/flyers/latest.png');

        $flyer = $this->pull($source);

        $this->assertNotNull($flyer);
        $this->assertSame(FlyerStatus::Draft, $flyer->status);
        $this->assertSame('Carrefour offers', $flyer->title_en);
        $this->assertSame('عروض كارفور', $flyer->title_ar);
        $this->assertSame(today(config('qdealme.timezone'))->addDays(6)->toDateString(), $flyer->valid_until->toDateString());
        $this->assertCount(1, $flyer->upload_files);
        Storage::disk('local')->assertExists($flyer->upload_files[0]);
        $this->assertSame(City::query()->active()->count(), $flyer->cities()->count());
        Queue::assertPushed(ProcessFlyerUpload::class, fn ($job) => $job->flyerId === $flyer->id);

        $source->refresh();
        $this->assertNotNull($source->last_pulled_at);
        $this->assertNotNull($source->last_pulled_hash);
        $this->assertNull($source->last_pull_error);
    }

    public function test_an_unchanged_flyer_is_not_pulled_twice(): void
    {
        Http::fake(['https://www.carrefourqatar.com/*' => Http::response($this->png(), 200)]);
        $source = $this->feedSource('https://www.carrefourqatar.com/flyers/latest.png');

        $this->assertNotNull($this->pull($source));
        $this->assertNull($this->pull($source->refresh()));
        $this->assertSame(1, Flyer::query()->count());
    }

    public function test_a_manifest_supplies_pages_titles_and_dates(): void
    {
        Http::fake([
            'https://www.carrefourqatar.com/flyers/current.json' => Http::response([
                'title_en' => 'Weekly Super Deals',
                'title_ar' => 'عروض الأسبوع',
                'valid_from' => '2026-10-22',
                'valid_until' => '2026-10-28',
                'pages' => ['https://cdn.example.net/p1.png', 'https://cdn.example.net/p2.png'],
            ]),
            'https://cdn.example.net/p1.png' => Http::response($this->png(10)),
            'https://cdn.example.net/p2.png' => Http::response($this->png(20)),
        ]);

        $flyer = $this->pull($this->feedSource('https://www.carrefourqatar.com/flyers/current.json'));

        $this->assertSame('Weekly Super Deals', $flyer->title_en);
        $this->assertSame('عروض الأسبوع', $flyer->title_ar);
        $this->assertSame('2026-10-22', $flyer->valid_from->toDateString());
        $this->assertSame('2026-10-28', $flyer->valid_until->toDateString());
        $this->assertCount(2, $flyer->upload_files);
    }

    public function test_the_link_must_be_on_the_stores_own_website(): void
    {
        $source = $this->feedSource('https://another-flyer-site.example/carrefour/latest.png');

        try {
            $this->pull($source);
            $this->fail('Expected the pull to be refused.');
        } catch (FeedPullException $e) {
            $this->assertStringContainsString('carrefourqatar.com', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, Flyer::query()->count());
        $this->assertNotNull($source->refresh()->last_pull_error);
    }

    public function test_a_store_without_a_website_cannot_be_pulled(): void
    {
        $this->expectException(FeedPullException::class);

        $this->pull($this->feedSource('https://www.carrefourqatar.com/latest.png', website: null));
    }

    public function test_private_addresses_and_plain_http_are_refused(): void
    {
        $this->resolvedIp = '10.0.0.5';

        try {
            $this->pull($this->feedSource('https://www.carrefourqatar.com/latest.png'));
            $this->fail('Expected a private address to be refused.');
        } catch (FeedPullException) {
        }

        $this->resolvedIp = '93.184.216.34';
        $this->expectException(FeedPullException::class);
        $this->pull($this->feedSource('http://www.carrefourqatar.com/latest.png'));
    }

    public function test_redirects_are_not_followed(): void
    {
        Http::fake(['https://www.carrefourqatar.com/*' => Http::response('', 302, ['Location' => 'https://elsewhere.example/x.png'])]);

        $this->expectException(FeedPullException::class);
        $this->pull($this->feedSource('https://www.carrefourqatar.com/latest.png'));
    }

    public function test_only_approved_feed_sources_can_be_pulled(): void
    {
        foreach ([
            $this->feedSource('https://www.carrefourqatar.com/latest.png', status: LicenseStatus::Pending),
            $this->feedSource('https://www.carrefourqatar.com/latest.png', type: SourceType::AdminUpload),
        ] as $source) {
            try {
                $this->pull($source);
                $this->fail('Expected the pull to be refused.');
            } catch (FeedPullException) {
            }
        }

        Http::assertNothingSent();
    }

    public function test_admins_pull_from_the_sources_table_and_land_on_the_draft(): void
    {
        Http::fake(['https://www.carrefourqatar.com/*' => Http::response($this->png(), 200)]);
        $source = $this->feedSource('https://www.carrefourqatar.com/latest.png');

        $this->actingAs($this->makeUser(UserRole::Reviewer));
        Livewire::test(ManageFlyerSources::class)->assertTableActionHidden('pull', $source);

        $this->actingAs($this->makeUser(UserRole::ContentAdmin));
        Livewire::test(ManageFlyerSources::class)
            ->callTableAction('pull', $source)
            ->assertRedirect();

        $this->assertSame(1, Flyer::query()->where('flyer_source_id', $source->id)->count());
    }

    public function test_admins_save_the_official_flyer_link_on_a_feed_source(): void
    {
        $this->actingAs($this->makeUser(UserRole::ContentAdmin));
        $retailer = $this->makeRetailer(['website_url' => 'https://www.monoprix.qa']);

        Livewire::test(ManageFlyerSources::class)
            ->callAction('create', [
                'retailer_id' => $retailer->id,
                'type' => SourceType::RetailerFeed->value,
                'feed_url' => 'https://www.monoprix.qa/flyer/current.json',
            ])
            ->assertHasNoActionErrors();

        $source = FlyerSource::query()->where('retailer_id', $retailer->id)->firstOrFail();
        $this->assertSame('https://www.monoprix.qa/flyer/current.json', $source->feed_url);
        $this->assertSame(LicenseStatus::Pending, $source->license_status);
    }

    public function test_store_directory_adds_hidden_stores_and_keeps_admin_edits(): void
    {
        $this->seed(RetailerDirectorySeeder::class);

        $carrefour = Retailer::query()->where('slug', 'carrefour')->firstOrFail();
        $this->assertFalse($carrefour->is_active);
        $this->assertSame('كارفور', $carrefour->name_ar);
        $this->assertSame(count(RetailerDirectorySeeder::STORES), Retailer::query()->whereIn('slug', array_column(RetailerDirectorySeeder::STORES, 0))->count());

        $carrefour->update(['is_active' => true, 'name_en' => 'Carrefour Qatar']);
        $this->seed(RetailerDirectorySeeder::class);

        $carrefour->refresh();
        $this->assertTrue($carrefour->is_active);
        $this->assertSame('Carrefour Qatar', $carrefour->name_en);
    }
}
