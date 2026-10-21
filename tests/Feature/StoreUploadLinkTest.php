<?php

namespace Tests\Feature;

use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FlyerSources\FlyerSourceResource;
use App\Filament\Resources\FlyerSources\Pages\ManageFlyerSources;
use App\Filament\Store\Pages\UploadFlyer;
use App\Jobs\ProcessFlyerUpload;
use App\Models\Flyer;
use App\Models\FlyerSource;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StoreUploadLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    private function parisSource(LicenseStatus $status = LicenseStatus::Approved): FlyerSource
    {
        $retailer = $this->makeRetailer(['name_en' => 'Paris Hypermarket', 'name_ar' => 'باريس هايبرماركت']);

        return $this->makeSource($retailer, $status);
    }

    private function token(FlyerSource $source): string
    {
        return basename($source->uploadUrl());
    }

    private function uploadPage(FlyerSource $source)
    {
        Filament::setCurrentPanel(Filament::getPanel('store'));

        return Livewire::test(UploadFlyer::class, ['token' => $this->token($source)]);
    }

    public function test_a_store_sends_its_flyer_through_its_private_link(): void
    {
        $source = $this->parisSource();

        $this->get($source->uploadUrl())->assertOk()->assertSee('باريس هايبرماركت');

        $this->uploadPage($source)
            ->fillForm([
                'images' => [
                    UploadedFile::fake()->createWithContent('page-1.png', $this->png(10)),
                    UploadedFile::fake()->createWithContent('page-2.png', $this->png(200)),
                ],
                'text' => 'عروض نهاية الأسبوع من ٢٢ إلى ٢٨ أكتوبر 2026',
                'title' => 'عروض نهاية الأسبوع',
            ])
            ->assertSchemaStateSet(['valid_from' => '2026-10-22', 'valid_until' => '2026-10-28'])
            ->call('send')
            ->assertHasNoFormErrors()
            ->assertSet('outcome', 'received');

        $flyer = Flyer::query()->sole();
        $this->assertSame(FlyerStatus::Draft, $flyer->status);
        $this->assertSame($source->id, $flyer->flyer_source_id);
        $this->assertSame('عروض نهاية الأسبوع', $flyer->title_ar);
        $this->assertSame('Paris Hypermarket offers', $flyer->title_en);
        $this->assertSame('2026-10-22', $flyer->valid_from->toDateString());
        $this->assertSame('2026-10-28', $flyer->valid_until->toDateString());
        $this->assertCount(2, $flyer->upload_files);
        $this->assertNotNull($source->refresh()->last_uploaded_at);
        Queue::assertPushed(ProcessFlyerUpload::class);
    }

    public function test_the_same_flyer_sent_twice_is_recognised(): void
    {
        $source = $this->parisSource();

        foreach (['received', 'duplicate'] as $expected) {
            $this->uploadPage($source)
                ->fillForm(['images' => [UploadedFile::fake()->createWithContent('page.png', $this->png(40))]])
                ->call('send')
                ->assertSet('outcome', $expected);
        }

        $this->assertSame(1, Flyer::query()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('uploads/flyers'));
    }

    public function test_pages_are_required(): void
    {
        $this->uploadPage($this->parisSource())
            ->fillForm(['images' => []])
            ->call('send')
            ->assertHasFormErrors(['images' => 'required']);

        $this->assertSame(0, Flyer::query()->count());
    }

    public function test_unknown_unapproved_revoked_and_replaced_links_do_not_open(): void
    {
        $this->get(UploadFlyer::url(str_repeat('ab', 24)))->assertNotFound();
        $this->get(UploadFlyer::url('not-a-token'))->assertNotFound();

        $pending = $this->parisSource(LicenseStatus::Pending);
        $pending->forceFill(['upload_token' => str_repeat('cd', 24)])->save();
        $this->get(UploadFlyer::url(str_repeat('cd', 24)))->assertNotFound();

        $source = $this->parisSource();
        $old = $source->uploadUrl();
        $source->rotateUploadToken();
        $this->get($old)->assertNotFound();
        $this->get($source->uploadUrl())->assertOk();

        $source->forceFill(['license_status' => LicenseStatus::Revoked])->save();
        $this->get($source->uploadUrl())->assertNotFound();
    }

    public function test_the_upload_page_opens_in_arabic_and_switches_to_english(): void
    {
        $source = $this->parisSource();

        $this->get($source->uploadUrl())
            ->assertOk()
            ->assertSee(__('store.upload.subheading', [], 'ar'))
            ->assertSee('English');

        $this->get($source->uploadUrl().'?lang=en')
            ->assertOk()
            ->assertSee(__('store.upload.subheading', [], 'en'))
            ->assertSee('Paris Hypermarket');

        // The choice is remembered for the visit.
        $this->get($source->uploadUrl())->assertSee(__('store.upload.subheading', [], 'en'));
    }

    public function test_staff_share_and_renew_a_stores_upload_link(): void
    {
        $source = $this->parisSource();
        $pending = $this->parisSource(LicenseStatus::Pending);

        $this->actingAs($this->makeUser(UserRole::Reviewer));
        Livewire::test(ManageFlyerSources::class)->assertTableActionHidden('uploadLink', $source);

        $this->actingAs($this->makeUser(UserRole::ContentAdmin));
        Livewire::test(ManageFlyerSources::class)
            ->assertTableActionHidden('uploadLink', $pending)
            ->mountTableAction('uploadLink', $source)
            ->assertMountedActionModalSee($source->refresh()->uploadUrl());

        $message = FlyerSourceResource::uploadLinkMessage($source);
        $this->assertStringContainsString('باريس هايبرماركت', $message);
        $this->assertStringContainsString('Paris Hypermarket', $message);
        $this->assertStringContainsString($source->uploadUrl(), $message);
        $this->assertSame(48, strlen((string) $source->upload_token));
    }
}
