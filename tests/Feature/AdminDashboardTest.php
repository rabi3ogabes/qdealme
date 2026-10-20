<?php

namespace Tests\Feature;

use App\Enums\FlyerStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AttentionPanel;
use App\Filament\Widgets\FlyerActivityChart;
use App\Filament\Widgets\KpiOverview;
use App\Filament\Widgets\ReviewQueue;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    public function test_dashboard_renders_with_every_widget_in_both_languages(): void
    {
        $this->makeFlyer(['status' => FlyerStatus::Published, 'published_at' => now()]);
        $this->makeFlyer(['status' => FlyerStatus::InReview], null, 1);

        foreach (['ar' => 'قائمة المراجعة', 'en' => 'Review queue'] as $locale => $queueLabel) {
            $admin = $this->makeUser(UserRole::SuperAdmin, ['locale' => $locale, 'name' => 'Rabii Test']);

            // AuthenticateSession ties the session to one user; start fresh for the next.
            $this->flushSession();
            $this->actingAs($admin)->get('/admin')
                ->assertOk()
                ->assertSee('qdealme-admin.css', false)
                ->assertSee('Rabii')
                ->assertSee($queueLabel);
        }
    }

    public function test_widgets_render_and_show_what_needs_attention(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin, ['locale' => 'en']));
        App::setLocale('en');
        $inReview = $this->makeFlyer(['status' => FlyerStatus::InReview], null, 1);

        Livewire::test(KpiOverview::class)->assertOk()->assertSee('Live flyers');
        Livewire::test(FlyerActivityChart::class)->assertOk()->set('filter', '7')->assertOk();
        Livewire::test(AttentionPanel::class)->assertOk()->assertSee('In review');
        Livewire::test(ReviewQueue::class)->assertOk()->assertCanSeeTableRecords([$inReview]);
    }

    public function test_attention_panel_says_all_clear_when_nothing_is_waiting(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin, ['locale' => 'en']));
        App::setLocale('en');

        Livewire::test(AttentionPanel::class)->assertSee('All caught up');
    }

    public function test_activity_counts_published_flyers_per_qatar_day(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin, ['locale' => 'en']));
        $this->makeFlyer(['status' => FlyerStatus::Published, 'published_at' => now()]);
        $this->makeFlyer(['status' => FlyerStatus::Published, 'published_at' => now()->subDays(40)]);

        $chart = Livewire::test(FlyerActivityChart::class)->set('filter', '7')->instance();
        $data = (fn () => $this->getData())->call($chart);

        $this->assertCount(7, $data['labels']);
        $this->assertSame(1, array_sum($data['datasets'][0]['data']));
    }

    public function test_english_admins_get_english_sidebar_groups(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin, ['locale' => 'en']))
            ->get('/admin/flyers')
            ->assertOk()
            ->assertSee('Content')
            ->assertDontSee('المحتوى');
    }

    public function test_admin_tables_use_latin_digits_in_arabic(): void
    {
        $this->makeFlyer(['status' => FlyerStatus::Published], null, 4);

        $this->actingAs($this->makeUser(UserRole::SuperAdmin, ['locale' => 'ar']))
            ->get('/admin/flyers')
            ->assertOk()
            ->assertDontSee('٤');
    }

    public function test_dashboard_pull_button_is_hidden_without_approved_feeds(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin, ['locale' => 'en']));

        Livewire::test(Dashboard::class)->assertActionHidden('pullFeeds')->assertActionVisible('newFlyer');
    }

    public function test_private_uploads_can_be_previewed_through_signed_links(): void
    {
        $this->assertTrue(config('filesystems.disks.local.serve'));
        $this->assertStringEndsWith('/private-files', (string) config('filesystems.disks.local.url'));
        $this->assertNotSame(
            parse_url((string) config('filesystems.disks.public.url'), PHP_URL_PATH),
            parse_url((string) config('filesystems.disks.local.url'), PHP_URL_PATH),
        );
    }

    public function test_the_admin_stylesheet_exists(): void
    {
        $this->assertFileExists(public_path('css/qdealme-admin.css'));
    }
}
