<?php

namespace Tests\Feature;

use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Enums\Verification;
use App\Filament\Resources\Flyers\Pages\CreateFlyer;
use App\Filament\Resources\Flyers\Pages\EditFlyer;
use App\Filament\Resources\FlyerSources\Pages\ManageFlyerSources;
use App\Filament\Resources\Offers\Pages\ManageOffers;
use App\Models\Flyer;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class AdminFlyerWorkflowTest extends TestCase
{
    private function jpegUpload(): UploadedFile
    {
        return UploadedFile::fake()->image('page.jpg', 800, 1100);
    }

    public function test_admin_creates_processes_and_publishes_a_flyer(): void
    {
        $this->actingAs($this->makeUser(UserRole::ContentAdmin));
        $retailer = $this->makeRetailer();
        $source = $this->makeSource($retailer);

        Livewire::test(CreateFlyer::class)
            ->fillForm([
                'retailer_id' => $retailer->id,
                'flyer_source_id' => $source->id,
                'title_ar' => 'عروض نهاية الأسبوع',
                'title_en' => 'Weekend Deals',
                'slug' => 'weekend-deals',
                'valid_from' => today()->toDateString(),
                'valid_until' => today()->addDays(6)->toDateString(),
                'cities' => [$this->doha()->id],
                'upload_files' => [$this->jpegUpload()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // The queue runs synchronously in tests, so processing has finished.
        $flyer = Flyer::query()->where('slug', 'weekend-deals')->firstOrFail();
        $this->assertSame(FlyerStatus::InReview, $flyer->status);
        $this->assertSame(1, $flyer->page_count);

        Livewire::test(EditFlyer::class, ['record' => $flyer->getKey()])
            ->callAction('approve');

        $this->assertSame(FlyerStatus::Published, $flyer->refresh()->status);
        $this->get($flyer->url())->assertOk();
    }

    public function test_form_rejects_a_source_that_is_not_approved(): void
    {
        $this->actingAs($this->makeUser(UserRole::ContentAdmin));
        $retailer = $this->makeRetailer();
        $pending = $this->makeSource($retailer, LicenseStatus::Pending);

        Livewire::test(CreateFlyer::class)
            ->fillForm([
                'retailer_id' => $retailer->id,
                'flyer_source_id' => $pending->id,
                'title_ar' => 'عروض',
                'title_en' => 'Deals',
                'slug' => 'deals',
                'valid_from' => today()->toDateString(),
                'valid_until' => today()->addDays(6)->toDateString(),
                'cities' => [$this->doha()->id],
                'upload_files' => [$this->jpegUpload()],
            ])
            ->call('create')
            ->assertHasFormErrors(['flyer_source_id']);

        $this->assertSame(0, Flyer::query()->count());
    }

    public function test_reviewers_cannot_approve_licences_but_admins_can(): void
    {
        $retailer = $this->makeRetailer();
        $source = $this->makeSource($retailer, LicenseStatus::Pending);

        $this->actingAs($this->makeUser(UserRole::Reviewer));
        Livewire::test(ManageFlyerSources::class)->assertTableActionHidden('approve', $source);

        $this->actingAs($admin = $this->makeUser(UserRole::SuperAdmin));
        Livewire::test(ManageFlyerSources::class)->callTableAction('approve', $source);

        $source->refresh();
        $this->assertSame(LicenseStatus::Approved, $source->license_status);
        $this->assertSame($admin->id, $source->approved_by);
    }

    public function test_bulk_verification_applies_sanity_rules(): void
    {
        $this->actingAs($admin = $this->makeUser(UserRole::Reviewer));
        $flyer = $this->makeFlyer();
        $good = $this->makeOffer($flyer, ['verification' => Verification::Unverified, 'price' => 10, 'old_price' => 12]);
        $bad = $this->makeOffer($flyer, ['verification' => Verification::Unverified, 'price' => 10, 'old_price' => 8]);

        Livewire::test(ManageOffers::class)->callTableBulkAction('verify', [$good, $bad]);

        $this->assertSame(Verification::Verified, $good->refresh()->verification);
        $this->assertSame($admin->id, $good->verified_by);
        $this->assertSame(Verification::Unverified, $bad->refresh()->verification);
    }
}
