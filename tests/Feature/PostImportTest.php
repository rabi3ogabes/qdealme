<?php

namespace Tests\Feature;

use App\Actions\Flyers\ImportFlyerFromPost;
use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Exceptions\PostImportException;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Flyers\Pages\ListFlyers;
use App\Filament\Resources\FlyerSources\Pages\ManageFlyerSources;
use App\Jobs\ProcessFlyerUpload;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Models\Retailer;
use App\Support\PostDates;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PostImportTest extends TestCase
{
    private const PAGE = 'https://www.facebook.com/ParisHypermarketQatar';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Queue::fake();
    }

    private function png(int $shade = 0): string
    {
        $im = imagecreatetruecolor(600, 900);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade, $shade, $shade));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /**
     * @return array<int, string>
     */
    private function storedImages(int ...$shades): array
    {
        return array_map(function (int $shade): string {
            $path = 'uploads/flyers/post-'.uniqid().'.png';
            Storage::disk('local')->put($path, $this->png($shade));

            return $path;
        }, $shades);
    }

    private function paris(LicenseStatus $status = LicenseStatus::Approved): FlyerSource
    {
        $retailer = $this->makeRetailer(['name_en' => 'Paris Hypermarket', 'name_ar' => 'باريس هايبرماركت']);

        return FlyerSource::query()->create([
            'retailer_id' => $retailer->id,
            'type' => SourceType::SocialMedia,
            'license_status' => $status,
            'page_url' => self::PAGE,
            'approved_at' => now(),
        ]);
    }

    private function import(FlyerSource $source, array $paths, ?string $url, array $details = []): Flyer
    {
        return app(ImportFlyerFromPost::class)->handle($source, $paths, $url, $details);
    }

    public function test_offer_dates_are_read_from_arabic_and_english_post_text(): void
    {
        $today = CarbonImmutable::create(2026, 10, 20, 0, 0, 0, config('qdealme.timezone'));
        $cases = [
            'Weekend deals 20 - 26 October! Shop now' => ['2026-10-20', '2026-10-26'],
            'عروض نهاية الأسبوع من ٢٠ إلى ٢٦ أكتوبر' => ['2026-10-20', '2026-10-26'],
            'العروض سارية من 22/10 حتى 28/10/2026' => ['2026-10-22', '2026-10-28'],
            'Offers valid 21-27/10' => ['2026-10-21', '2026-10-27'],
            'Valid from Oct 21st to Oct 27th, 2026' => ['2026-10-21', '2026-10-27'],
            'October 23 - 29 only at our Doha branches' => ['2026-10-23', '2026-10-29'],
            'Hurry, valid until 30 October' => ['2026-10-20', '2026-10-30'],
            'عرض ٢٨ ديسمبر - ٣ يناير' => ['2026-12-28', '2027-01-03'],
            'From 24.10.2026 to 30.10.2026' => ['2026-10-24', '2026-10-30'],
        ];

        foreach ($cases as $text => [$from, $until]) {
            $dates = PostDates::find($text, $today);

            $this->assertNotNull($dates, $text);
            $this->assertSame($from, $dates['from']->toDateString(), $text);
            $this->assertSame($until, $dates['until']->toDateString(), $text);
        }

        foreach (['Rice 5 kg only 12.50 QR', 'Buy 1 get 1 free', 'Call 4444 5555', '', 'Ended 1 October'] as $text) {
            $this->assertNull(PostDates::find($text, $today), $text);
        }
    }

    public function test_the_store_is_recognised_from_the_post_link(): void
    {
        $source = $this->paris();
        $website = $this->makeRetailer(['website_url' => 'https://www.carrefourqatar.com']);
        $websiteSource = $this->makeSource($website);

        $this->assertTrue($source->is(ImportFlyerFromPost::guessSource('https://www.facebook.com/ParisHypermarketQatar/posts/pfbid02abc')));
        $this->assertTrue($source->is(ImportFlyerFromPost::guessSource('https://m.facebook.com/parishypermarketqatar/photos/a.1/2/')));
        $this->assertTrue($websiteSource->is(ImportFlyerFromPost::guessSource('https://carrefourqatar.com/offers/weekly')));

        // Share links do not name the page, so staff pick the store.
        $this->assertNull(ImportFlyerFromPost::guessSource('https://www.facebook.com/share/p/1BwJKS94ay/'));
        $this->assertNull(ImportFlyerFromPost::guessSource('https://www.instagram.com/p/C1abc/'));
        $this->assertNull(ImportFlyerFromPost::guessSource('not a link'));

        $this->assertSame('facebook.com/id:100064', ImportFlyerFromPost::pageKey('https://www.facebook.com/permalink.php?story_fbid=1&id=100064'));
        $this->assertSame(
            'https://www.facebook.com/share/p/1BwJKS94ay/',
            ImportFlyerFromPost::cleanUrl(' https://www.facebook.com/share/p/1BwJKS94ay/?mibextid=oFDknk&fbclid=x ')
        );
    }

    public function test_a_post_becomes_a_draft_with_its_link_and_dates(): void
    {
        $source = $this->paris();
        $paths = $this->storedImages(10, 200);

        $flyer = $this->import($source, $paths, 'https://www.facebook.com/share/p/1BwJKS94ay/?mibextid=abc', [
            'valid_from' => CarbonImmutable::parse('2026-10-22'),
            'valid_until' => CarbonImmutable::parse('2026-10-28'),
        ]);

        $this->assertSame(FlyerStatus::Draft, $flyer->status);
        $this->assertSame($source->id, $flyer->flyer_source_id);
        $this->assertSame('Paris Hypermarket offers', $flyer->title_en);
        $this->assertSame('عروض باريس هايبرماركت', $flyer->title_ar);
        $this->assertSame('2026-10-22', $flyer->valid_from->toDateString());
        $this->assertSame('2026-10-28', $flyer->valid_until->toDateString());
        $this->assertSame($paths, $flyer->upload_files);
        $this->assertSame('https://www.facebook.com/share/p/1BwJKS94ay/', $flyer->source_post_url);
        $this->assertSame(64, strlen((string) $flyer->content_hash));
        $this->assertNotEmpty($flyer->cities()->pluck('cities.id'));
        Queue::assertPushed(ProcessFlyerUpload::class);
    }

    public function test_the_same_post_is_never_imported_twice(): void
    {
        $source = $this->paris();
        $first = $this->import($source, $this->storedImages(10, 200), 'https://www.facebook.com/share/p/1BwJKS94ay/');

        // The same images, shared under another link.
        try {
            $this->import($source, $this->storedImages(10, 200), 'https://www.facebook.com/share/p/OTHER/');
            $this->fail('The same images were imported twice.');
        } catch (PostImportException $e) {
            $this->assertTrue($first->is($e->existing));
        }

        // The same link, with tracking parameters.
        try {
            $this->import($source, $this->storedImages(90), 'https://www.facebook.com/share/p/1BwJKS94ay/?fbclid=123');
            $this->fail('The same post link was imported twice.');
        } catch (PostImportException $e) {
            $this->assertTrue($first->is($e->existing));
        }

        // Pages in a different order are a different flyer.
        $this->import($source, $this->storedImages(200, 10), 'https://www.facebook.com/share/p/NEW/');
        $this->assertSame(2, Flyer::query()->count());
    }

    public function test_only_approved_sources_can_import(): void
    {
        $source = $this->paris(LicenseStatus::Pending);

        $this->expectException(PostImportException::class);
        $this->import($source, $this->storedImages(10), 'https://www.facebook.com/share/p/1BwJKS94ay/');
    }

    public function test_staff_import_a_post_from_the_dashboard_and_land_on_the_draft(): void
    {
        $source = $this->paris();
        $this->actingAs($this->makeUser(UserRole::ContentAdmin));

        Livewire::test(Dashboard::class)
            ->mountAction('importPost')
            ->fillForm(['post_url' => self::PAGE.'/posts/pfbid0xyz'])
            ->assertSchemaStateSet(['retailer_id' => $source->retailer_id, 'flyer_source_id' => $source->id])
            ->fillForm(['caption' => 'عروض باريس من ٢٢ إلى ٢٨ أكتوبر 2026'])
            ->assertSchemaStateSet(['valid_from' => '2026-10-22', 'valid_until' => '2026-10-28'])
            ->fillForm(['images' => [
                UploadedFile::fake()->createWithContent('page-1.png', $this->png(10)),
                UploadedFile::fake()->createWithContent('page-2.png', $this->png(200)),
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified()
            ->assertRedirect();

        $flyer = Flyer::query()->sole();
        $this->assertSame($source->id, $flyer->flyer_source_id);
        $this->assertSame('2026-10-22', $flyer->valid_from->toDateString());
        $this->assertCount(2, $flyer->upload_files);
        $this->assertSame(self::PAGE.'/posts/pfbid0xyz', $flyer->source_post_url);
    }

    public function test_a_duplicate_import_from_the_flyers_list_is_refused_and_cleaned_up(): void
    {
        $source = $this->paris();
        $this->import($source, $this->storedImages(10), 'https://www.facebook.com/share/p/1BwJKS94ay/');
        $this->actingAs($this->makeUser(UserRole::ContentAdmin));
        $before = count(Storage::disk('local')->allFiles('uploads/flyers'));

        Livewire::test(ListFlyers::class)
            ->callAction('importPost', [
                'post_url' => 'https://www.facebook.com/share/p/1BwJKS94ay/',
                'retailer_id' => $source->retailer_id,
                'flyer_source_id' => $source->id,
                'images' => [UploadedFile::fake()->createWithContent('page.png', $this->png(50))],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('admin.import.failed'))
            ->assertNoRedirect();

        $this->assertSame(1, Flyer::query()->count());
        $this->assertSame($before, count(Storage::disk('local')->allFiles('uploads/flyers')));
    }

    public function test_stores_without_an_approved_source_cannot_be_chosen(): void
    {
        $this->paris();
        $unlicensed = $this->makeRetailer();
        $this->makeSource($unlicensed, LicenseStatus::Pending);
        $this->actingAs($this->makeUser(UserRole::ContentAdmin));

        Livewire::test(Dashboard::class)
            ->callAction('importPost', [
                'post_url' => 'https://www.facebook.com/share/p/1BwJKS94ay/',
                'retailer_id' => $unlicensed->id,
                'images' => [UploadedFile::fake()->createWithContent('page.png', $this->png())],
            ])
            ->assertHasActionErrors(['retailer_id', 'flyer_source_id']);

        $this->assertSame(0, Flyer::query()->count());
    }

    public function test_admins_record_the_stores_official_page_on_a_social_media_source(): void
    {
        $this->actingAs($this->makeUser(UserRole::ContentAdmin));
        $retailer = $this->makeRetailer();

        Livewire::test(ManageFlyerSources::class)
            ->callAction('create', [
                'retailer_id' => $retailer->id,
                'type' => SourceType::SocialMedia->value,
                'page_url' => self::PAGE,
            ])
            ->assertHasNoActionErrors();

        $source = FlyerSource::query()->where('retailer_id', $retailer->id)->sole();
        $this->assertSame(self::PAGE, $source->page_url);
        $this->assertSame(LicenseStatus::Pending, $source->license_status);
        $this->assertInstanceOf(Retailer::class, $source->retailer);
    }
}
