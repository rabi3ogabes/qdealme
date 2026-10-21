<?php

namespace Tests;

use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\RetailerType;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Enums\Verification;
use App\Models\City;
use App\Models\Country;
use App\Models\Flyer;
use App\Models\FlyerPage;
use App\Models\FlyerSource;
use App\Models\Offer;
use App\Models\Retailer;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seedLocations = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        Storage::fake('local');

        if ($this->seedLocations) {
            $this->seed([LocationSeeder::class, CategorySeeder::class]);
        }
    }

    protected function doha(): City
    {
        return City::query()->where('slug', 'doha')->with('country')->firstOrFail();
    }

    protected function qatar(): Country
    {
        return Country::query()->where('slug', 'qatar')->firstOrFail();
    }

    protected function makeRetailer(array $attributes = []): Retailer
    {
        static $n = 0;
        $n++;

        return Retailer::query()->create($attributes + [
            'slug' => 'test-store-'.$n,
            'name_en' => 'Test Store '.$n,
            'name_ar' => 'متجر تجريبي '.$n,
            'type' => RetailerType::Supermarket,
            'is_active' => true,
        ]);
    }

    protected function makeSource(Retailer $retailer, LicenseStatus $status = LicenseStatus::Approved): FlyerSource
    {
        return FlyerSource::query()->create([
            'retailer_id' => $retailer->id,
            'type' => SourceType::RetailerUpload,
            'license_status' => $status,
        ]);
    }

    /**
     * A flyer with pages, attached to Doha.
     */
    protected function makeFlyer(array $attributes = [], ?Retailer $retailer = null, int $pages = 2): Flyer
    {
        $retailer ??= $this->makeRetailer();
        $source = $this->makeSource($retailer);
        $today = today(config('qdealme.timezone'));

        $flyer = Flyer::query()->create($attributes + [
            'retailer_id' => $retailer->id,
            'flyer_source_id' => $source->id,
            'title_en' => 'Weekly Deals',
            'title_ar' => 'عروض الأسبوع',
            'valid_from' => $today->copy()->subDay(),
            'valid_until' => $today->copy()->addDays(5),
            'status' => FlyerStatus::Published,
            'published_at' => now(),
        ]);

        for ($i = 1; $i <= $pages; $i++) {
            FlyerPage::query()->create([
                'flyer_id' => $flyer->id,
                'number' => $i,
                'original_path' => "flyers/{$flyer->id}/pages/{$i}/original.jpg",
                'width' => 1080,
                'height' => 1440,
                'derivatives' => ['webp' => [320 => "flyers/{$flyer->id}/pages/{$i}/320.webp"], 'jpg' => [320 => "flyers/{$flyer->id}/pages/{$i}/320.jpg"]],
            ]);
        }

        $flyer->forceFill(['page_count' => $pages, 'cover_page_id' => $flyer->pages()->value('id')])->save();
        $flyer->cities()->attach($this->doha()->id);

        return $flyer->refresh();
    }

    protected function makeOffer(Flyer $flyer, array $attributes = []): Offer
    {
        return Offer::query()->create($attributes + [
            'flyer_id' => $flyer->id,
            'flyer_page_id' => $flyer->pages()->value('id'),
            'retailer_id' => $flyer->retailer_id,
            'title_en' => 'Basmati Rice 5 kg',
            'title_ar' => 'أرز بسمتي ٥ كغ',
            'price' => 32.95,
            'old_price' => 42.00,
            'size_value' => 5,
            'size_unit' => 'kg',
            'bbox' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.4, 'h' => 0.25],
            'verification' => Verification::Verified,
            'verified_at' => now(),
        ]);
    }

    protected function makeUser(UserRole $role = UserRole::Shopper, array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::query()->create($attributes + [
            'name' => 'User '.$n,
            'email' => "user{$n}@example.test",
            'password' => 'password-123',
            'role' => $role,
        ]);
    }

    /**
     * Public URL helper that fills in locale/country/city.
     */
    protected function cityUrl(string $path = '', string $locale = 'ar', string $city = 'doha'): string
    {
        return '/'.$locale.'/qatar/'.$city.($path !== '' ? '/'.ltrim($path, '/') : '');
    }
}
