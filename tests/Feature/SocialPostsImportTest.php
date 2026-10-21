<?php

namespace Tests\Feature;

use App\Actions\Flyers\PullAllFeeds;
use App\Actions\Flyers\PullSocialPosts;
use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Exceptions\FeedPullException;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Flyers\FlyerResource;
use App\Filament\Resources\FlyerSources\Pages\ManageFlyerSources;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Services\RetailerFeedClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SocialPostsImportTest extends TestCase
{
    private const GRAPH = 'https://graph.facebook.com/v23.0';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00', 'Asia/Qatar'));
        $this->app->instance(RetailerFeedClient::class, new RetailerFeedClient(fn () => ['157.240.1.1']));

        config([
            'qdealme.meta.access_token' => 'test-token',
            'qdealme.meta.app_secret' => 'test-secret',
            'qdealme.meta.instagram_account_id' => '17841400000',
        ]);
    }

    private function png(int $shade = 0): string
    {
        $im = imagecreatetruecolor(600, 900);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade, $shade, $shade));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function socialSource(string $pageUrl = 'https://www.facebook.com/ParisHypermarketQatar', LicenseStatus $status = LicenseStatus::Approved): FlyerSource
    {
        $retailer = $this->makeRetailer(['name_en' => 'Paris Hypermarket', 'name_ar' => 'باريس هايبرماركت']);

        return FlyerSource::query()->create([
            'retailer_id' => $retailer->id,
            'type' => SourceType::SocialMedia,
            'license_status' => $status,
            'page_url' => $pageUrl,
            'approved_at' => now(),
        ]);
    }

    private function photo(string $id): array
    {
        return ['type' => 'photo', 'media_type' => 'photo', 'target' => ['id' => $id], 'media' => ['image' => ['src' => "https://scontent.xx.fbcdn.net/{$id}-720.jpg", 'width' => 720, 'height' => 1018]]];
    }

    private function fakeFacebookPage(): void
    {
        Http::fake([
            self::GRAPH.'/parishypermarketqatar?*' => Http::response(['id' => '1001']),
            self::GRAPH.'/1001/posts?*' => Http::response(['data' => [
                [
                    'id' => '1001_1',
                    'message' => "عروض نهاية الأسبوع من ٢٢ إلى ٢٨ أكتوبر\nWeekend offers",
                    'created_time' => '2026-10-20T05:00:00+0000',
                    'permalink_url' => 'https://www.facebook.com/ParisHypermarketQatar/posts/pfbid0aaa',
                    'attachments' => ['data' => [[
                        'type' => 'album', 'media_type' => 'album',
                        'subattachments' => ['data' => [$this->photo('p1'), $this->photo('p2')]],
                    ]]],
                ],
                [
                    'id' => '1001_2',
                    'message' => 'Happy National Day!',
                    'created_time' => '2026-10-19T05:00:00+0000',
                    'permalink_url' => 'https://www.facebook.com/ParisHypermarketQatar/posts/pfbid0bbb',
                    'attachments' => ['data' => [$this->photo('p3')]],
                ],
                [
                    'id' => '1001_3',
                    'message' => 'Mega offers',
                    'created_time' => '2026-09-01T05:00:00+0000',
                    'permalink_url' => 'https://www.facebook.com/ParisHypermarketQatar/posts/pfbid0ccc',
                    'attachments' => ['data' => [$this->photo('p4')]],
                ],
                [
                    'id' => '1001_4',
                    'message' => 'Watch our offers video',
                    'created_time' => '2026-10-20T06:00:00+0000',
                    'permalink_url' => 'https://www.facebook.com/ParisHypermarketQatar/videos/1',
                    'attachments' => ['data' => [['type' => 'video_inline', 'media_type' => 'video']]],
                ],
            ]]),
            self::GRAPH.'/?ids=*' => Http::response([
                'p1' => ['id' => 'p1', 'images' => [['source' => 'https://scontent.xx.fbcdn.net/p1-720.jpg', 'width' => 720], ['source' => 'https://scontent.xx.fbcdn.net/p1-2048.jpg', 'width' => 1448]]],
                'p2' => ['id' => 'p2', 'images' => [['source' => 'https://scontent.xx.fbcdn.net/p2-2048.jpg', 'width' => 1448]]],
            ]),
            'https://scontent.xx.fbcdn.net/p1-*' => Http::response($this->png(10), 200, ['Content-Type' => 'image/png']),
            'https://scontent.xx.fbcdn.net/p2-*' => Http::response($this->png(200), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    public function test_new_flyer_posts_on_a_facebook_page_become_drafts_by_themselves(): void
    {
        $this->fakeFacebookPage();
        $source = $this->socialSource();

        $drafts = app(PullSocialPosts::class)->handle($source);

        $this->assertCount(1, $drafts);
        $flyer = $drafts[0]->refresh();
        $this->assertSame(FlyerStatus::Draft, $flyer->status);
        $this->assertSame($source->id, $flyer->flyer_source_id);
        $this->assertSame('https://www.facebook.com/ParisHypermarketQatar/posts/pfbid0aaa', $flyer->source_post_url);
        $this->assertSame('2026-10-22', $flyer->valid_from->toDateString());
        $this->assertSame('2026-10-28', $flyer->valid_until->toDateString());
        $this->assertCount(2, $flyer->upload_files);
        $this->assertSame('1001', $source->refresh()->social_page_id);
        $this->assertNotNull($source->last_pulled_at);
        $this->assertNull($source->last_pull_error);

        // The best resolution is used, and every call carries the app secret proof.
        Http::assertSent(fn (Request $request) => $request->url() === 'https://scontent.xx.fbcdn.net/p1-2048.jpg');
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), self::GRAPH.'/1001/posts')
            && $request['appsecret_proof'] === hash_hmac('sha256', 'test-token', 'test-secret'));

        // Nothing is imported twice: the second check only lists the page's posts.
        $this->assertSame([], app(PullSocialPosts::class)->handle($source->refresh()));
        $this->assertSame(1, Flyer::query()->count());
        Http::assertSentCount(6);
    }

    public function test_instagram_posts_are_read_through_business_discovery(): void
    {
        Http::fake([
            self::GRAPH.'/17841400000?*' => Http::response(['business_discovery' => ['media' => ['data' => [
                [
                    'id' => 'ig1',
                    'caption' => 'Weekend offers 20 - 26 October',
                    'media_type' => 'CAROUSEL_ALBUM',
                    'permalink' => 'https://www.instagram.com/p/C0ffers/',
                    'timestamp' => '2026-10-20T05:00:00+0000',
                    'children' => ['data' => [
                        ['media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/ig1-a.jpg'],
                        ['media_type' => 'VIDEO', 'media_url' => 'https://scontent.cdninstagram.com/ig1-b.mp4'],
                        ['media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/ig1-c.jpg'],
                    ]],
                ],
                ['id' => 'ig2', 'caption' => 'Offers reel', 'media_type' => 'VIDEO', 'permalink' => 'https://www.instagram.com/reel/x/', 'timestamp' => '2026-10-20T05:00:00+0000'],
            ]]]]),
            'https://scontent.cdninstagram.com/ig1-a.jpg' => Http::response($this->png(30)),
            'https://scontent.cdninstagram.com/ig1-c.jpg' => Http::response($this->png(90)),
        ]);
        $source = $this->socialSource('https://www.instagram.com/parishypermarket/');

        $drafts = app(PullSocialPosts::class)->handle($source);

        $this->assertCount(1, $drafts);
        $this->assertSame('https://www.instagram.com/p/C0ffers/', $drafts[0]->source_post_url);
        $this->assertSame('2026-10-26', $drafts[0]->valid_until->toDateString());
        $this->assertCount(2, $drafts[0]->upload_files);
        Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'business_discovery.username(parishypermarket)'));
    }

    public function test_metas_answers_are_explained_to_staff(): void
    {
        Http::fake([self::GRAPH.'/*' => Http::sequence()
            ->push(['error' => [
                'message' => "(#10) To use 'Page Public Content Access', your use of this endpoint must be reviewed and approved by Facebook.",
                'code' => 10,
            ]], 400)
            ->push(['error' => ['message' => 'Error validating access token', 'code' => 190]], 400)
            ->push(['error' => ['message' => "Object with ID 'nosuchpage' does not exist", 'code' => 100]], 404)]);

        $expected = ['social_needs_approval', 'social_token', 'social_page_not_found'];

        foreach ($expected as $reason) {
            $source = $this->socialSource();

            try {
                app(PullSocialPosts::class)->handle($source);
                $this->fail('An error from Meta must be reported.');
            } catch (FeedPullException $e) {
                $this->assertSame(__('feed.errors.'.$reason), $e->getMessage());
            }

            $this->assertSame(__('feed.errors.'.$reason), $source->refresh()->last_pull_error);
        }
    }

    public function test_social_pages_join_the_daily_pull_only_when_meta_is_set_up(): void
    {
        $this->fakeFacebookPage();
        $source = $this->socialSource();
        $this->socialSource(status: LicenseStatus::Pending);

        $this->assertSame([$source->id], PullAllFeeds::pullable()->pluck('id')->all());

        $summary = app(PullAllFeeds::class)->handle();
        $this->assertSame(1, $summary['created']);
        $this->assertSame([], $summary['failed']);

        config(['qdealme.meta.access_token' => '']);
        $this->assertFalse(PullAllFeeds::pullable()->exists());
        $this->assertFalse(PullAllFeeds::isPullable($source));
    }

    public function test_staff_check_a_connected_page_from_the_sources_table_and_dashboard(): void
    {
        $this->fakeFacebookPage();
        $source = $this->socialSource();

        $this->actingAs($this->makeUser(UserRole::ContentAdmin));
        Livewire::test(ManageFlyerSources::class)
            ->assertTableActionVisible('pull', $source)
            ->callTableAction('pull', $source)
            ->assertRedirect();

        $this->assertSame(1, Flyer::query()->count());
        $this->assertTrue($source->is(FlyerResource::connectedSource($source->id)));

        Livewire::test(Dashboard::class)
            ->assertActionVisible('pullFeeds')
            ->mountAction('importPost')
            ->fillForm(['retailer_id' => $source->retailer_id])
            ->assertSchemaStateSet(['flyer_source_id' => $source->id])
            ->assertMountedActionModalSee(__('admin.import.connected'));

        config(['qdealme.meta.access_token' => '']);
        $this->assertNull(FlyerResource::connectedSource($source->id));
    }

    public function test_unreadable_page_links_are_explained(): void
    {
        config(['qdealme.meta.instagram_account_id' => '']);

        foreach (['https://www.instagram.com/parishypermarket/', 'https://www.facebook.com/share/p/1BwJKS94ay/'] as $link) {
            try {
                app(PullSocialPosts::class)->handle($this->socialSource($link));
                $this->fail("{$link} cannot be read and must be explained.");
            } catch (FeedPullException $e) {
                $this->assertSame(__('feed.errors.social_unsupported_page'), $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_only_posts_that_look_like_flyers_are_imported(): void
    {
        $this->assertTrue(PullSocialPosts::looksLikeFlyer('anything', hasDates: true));
        $this->assertTrue(PullSocialPosts::looksLikeFlyer('خصم 50% على جميع الفواكه', hasDates: false));
        $this->assertTrue(PullSocialPosts::looksLikeFlyer('MEGA OFFERS this weekend', hasDates: false));
        $this->assertFalse(PullSocialPosts::looksLikeFlyer('Happy National Day!', hasDates: false));
        $this->assertFalse(PullSocialPosts::looksLikeFlyer('', hasDates: false));
    }
}
