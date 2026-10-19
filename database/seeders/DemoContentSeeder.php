<?php

namespace Database\Seeders;

use App\Actions\Flyers\PublishFlyer;
use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\RetailerType;
use App\Enums\SourceType;
use App\Enums\Verification;
use App\Models\Branch;
use App\Models\Category;
use App\Models\City;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Models\Offer;
use App\Models\Retailer;
use App\Models\User;
use App\Services\FlyerImageProcessor;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\DemoFlyerPainter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo content for local development and previews.
 *
 * Every store below is FICTIONAL. Their flyers are painted on the fly by
 * DemoFlyerPainter, so no real retailer's artwork or data is ever used.
 * Each demo source is recorded as an approved admin upload, which keeps the
 * licence-enforcement rule intact even for demo data.
 */
class DemoContentSeeder extends Seeder
{
    /** [slug, name_en, name_ar, type, color, accent, initials, website, whatsapp] */
    private const RETAILERS = [
        ['al-bayt-hypermarket', 'Al Bayt Hypermarket', 'هايبرماركت البيت', 'hypermarket', '#0f766e', '#f2c94c', 'AB', 'https://example.com/al-bayt', '+97440000001'],
        ['doha-fresh', 'Doha Fresh', 'دوحة فريش', 'supermarket', '#15803d', '#fde68a', 'DF', null, '+97440000002'],
        ['corniche-mart', 'Corniche Mart', 'كورنيش مارت', 'supermarket', '#b45309', '#fef3c7', 'CM', 'https://example.com/corniche', null],
        ['majlis-family-market', 'Majlis Family Market', 'سوق المجلس العائلي', 'hypermarket', '#4338ca', '#fcd34d', 'MF', 'https://example.com/majlis', '+97440000004'],
        ['souq-daily', 'Souq Daily', 'سوق ديلي', 'supermarket', '#be123c', '#fde68a', 'SD', null, '+97440000005'],
        ['pearl-electronics', 'Pearl Electronics', 'بيرل للإلكترونيات', 'electronics', '#1d4ed8', '#fbbf24', 'PE', 'https://example.com/pearl', null],
        ['technest', 'TechNest', 'تك نست', 'electronics', '#6d28d9', '#a7f3d0', 'TN', 'https://example.com/technest', '+97440000007'],
        ['al-waha-pharmacy', 'Al Waha Pharmacy', 'صيدلية الواحة', 'pharmacy', '#0e7490', '#bbf7d0', 'AW', null, '+97440000008'],
        ['lusail-home', 'Lusail Home', 'لوسيل هوم', 'furniture', '#92400e', '#fde68a', 'LH', 'https://example.com/lusail-home', null],
        ['zahra-fashion', 'Zahra Fashion', 'أزياء زهرة', 'fashion', '#9d174d', '#fbcfe8', 'ZF', 'https://example.com/zahra', null],
    ];

    /** [category, en, ar, brand, size_value, size_unit, size_label, price, old_price, shape] */
    private const GROCERY = [
        ['fruits-vegetables', 'Fresh Tomatoes', 'طماطم طازجة', null, 1, 'kg', '1 kg', 3.75, 5.50, 'round'],
        ['fruits-vegetables', 'Bananas', 'موز', null, 1, 'kg', '1 kg', 4.95, 6.25, 'round'],
        ['fruits-vegetables', 'Red Apples', 'تفاح أحمر', null, 1, 'kg', '1 kg', 6.50, 8.75, 'round'],
        ['dairy-eggs', 'Fresh Full-Fat Milk', 'حليب طازج كامل الدسم', 'Al Rawda Farms', 2, 'l', '2 L', 10.50, 12.00, 'bottle'],
        ['dairy-eggs', 'Large White Eggs 30 pcs', 'بيض أبيض كبير ٣٠ حبة', 'Sunrise', 30, 'pc', '30 pcs', 17.95, 21.00, 'box'],
        ['dairy-eggs', 'Natural Yoghurt', 'زبادي طبيعي', 'Al Rawda Farms', 1, 'kg', '1 kg', 8.25, 9.95, 'box'],
        ['meat-poultry-fish', 'Whole Fresh Chicken', 'دجاج كامل طازج', 'Gulf Farms', 1000, 'g', '1 kg', 13.50, 16.00, 'round'],
        ['meat-poultry-fish', 'Fresh Hammour Fillet', 'فيليه هامور طازج', null, 1, 'kg', '1 kg', 59.00, 72.00, 'box'],
        ['pantry-rice', 'Golden Grain Basmati Rice', 'أرز بسمتي جولدن جرين', 'Golden Grain', 5, 'kg', '5 kg', 32.95, 42.00, 'box'],
        ['pantry-rice', 'Sunflower Oil', 'زيت دوار الشمس', 'Sahra', 1.8, 'l', '1.8 L', 14.50, 18.25, 'bottle'],
        ['pantry-rice', 'Khalas Dates', 'تمر خلاص', 'Nakheel', 1, 'kg', '1 kg', 18.00, 24.00, 'box'],
        ['beverages', 'Mineral Water 12 x 500 ml', 'مياه معدنية ١٢ × ٥٠٠ مل', 'Rayyan Springs', 6, 'l', '12 x 500 ml', 7.50, 9.00, 'bottle'],
        ['beverages', 'Orange Juice', 'عصير برتقال', 'Sunrise', 1.75, 'l', '1.75 L', 9.25, 11.50, 'bottle'],
        ['frozen', 'Frozen Mixed Vegetables', 'خضار مشكلة مجمدة', 'Frost Valley', 900, 'g', '900 g', 8.25, 11.00, 'box'],
        ['bakery-confectionery', 'Arabic Bread 6 pcs', 'خبز عربي ٦ حبات', 'Doha Bakery', 6, 'pc', '6 pcs', 2.50, 3.00, 'box'],
        ['snacks', 'Potato Chips Family Pack', 'رقائق بطاطس عائلي', 'Crunchy', 170, 'g', '170 g', 6.95, 9.50, 'box'],
        ['laundry-cleaning', 'Laundry Detergent Powder', 'مسحوق غسيل', 'Bright', 3, 'kg', '3 kg', 24.95, 34.00, 'box'],
        ['tissue-disposables', 'Facial Tissues 5 x 200', 'مناديل وجه ٥ × ٢٠٠', 'Soft Cloud', 5, 'pc', '5 boxes', 12.95, 17.50, 'box'],
        ['baby-mother', 'Baby Diapers Size 4', 'حفاضات أطفال مقاس ٤', 'Tiny Steps', 64, 'pc', '64 pcs', 54.00, 69.00, 'box'],
    ];

    private const ELECTRONICS = [
        ['mobiles', 'Nova X12 Smartphone 256 GB', 'هاتف نوفا X12 سعة ٢٥٦ جيجا', 'Nova', null, null, '256 GB', 2399, 2899, 'phone'],
        ['mobiles', 'Vexa Lite 5G 128 GB', 'هاتف فيكسا لايت 5G سعة ١٢٨ جيجا', 'Vexa', null, null, '128 GB', 849, 1099, 'phone'],
        ['tvs', '55 inch 4K UHD Smart TV', 'تلفزيون ذكي ٥٥ بوصة 4K', 'Orbit', null, null, '55 inch', 1499, 1999, 'screen'],
        ['tvs', '65 inch QLED Smart TV', 'تلفزيون ذكي ٦٥ بوصة QLED', 'Orbit', null, null, '65 inch', 2799, 3499, 'screen'],
        ['computers-laptops', '14 inch Ultrabook 16 GB / 512 GB', 'لابتوب ألترابوك ١٤ بوصة', 'Kairo', null, null, '16 GB / 512 GB', 2799, 3399, 'screen'],
        ['tablets', '11 inch Tablet 128 GB', 'جهاز لوحي ١١ بوصة ١٢٨ جيجا', 'Nova', null, null, '128 GB', 1149, 1399, 'phone'],
        ['smart-watches', 'Smart Watch with GPS', 'ساعة ذكية مع GPS', 'Vexa', null, null, '45 mm', 599, 799, 'phone'],
        ['accessories', 'Wireless Noise-Cancelling Headphones', 'سماعات لاسلكية بعزل الضوضاء', 'Kairo', null, null, 'Bluetooth 5.3', 449, 699, 'round'],
        ['small-appliances', 'Air Fryer 5.5 L', 'قلاية هوائية ٥٫٥ لتر', 'Sahra Home', null, null, '5.5 L', 279, 399, 'appliance'],
        ['kitchen-appliances', 'Espresso Coffee Machine', 'آلة قهوة إسبريسو', 'Sahra Home', null, null, '15 bar', 899, 1199, 'appliance'],
        ['large-appliances', 'Split Air Conditioner 1.5 Ton', 'مكيف سبليت ١٫٥ طن', 'Orbit', null, null, '18,000 BTU', 1899, 2499, 'appliance'],
        ['large-appliances', 'Front Load Washer 9 kg', 'غسالة تحميل أمامي ٩ كغ', 'Orbit', null, null, '9 kg', 1599, 1999, 'appliance'],
        ['gaming', 'Gaming Console Bundle', 'جهاز ألعاب مع ألعاب', 'Kairo', null, null, '1 TB', 1899, 2199, 'box'],
        ['monitors-projectors', '27 inch QHD Monitor', 'شاشة ٢٧ بوصة QHD', 'Kairo', null, null, '27 inch', 799, 1049, 'screen'],
        ['cameras', 'Mirrorless Camera Kit', 'كاميرا ميرورليس مع عدسة', 'Vexa', null, null, '24 MP', 3499, 4199, 'box'],
        ['printers', 'Wireless Inkjet Printer', 'طابعة حبر لاسلكية', 'Orbit', null, null, 'Wi-Fi', 249, 349, 'box'],
    ];

    private const PHARMACY = [
        ['health-beauty', 'Moisturising Cream', 'كريم مرطب', 'Pure Skin', 400, 'ml', '400 ml', 16.75, 22.00, 'bottle'],
        ['health-beauty', 'Vitamin C 1000 mg', 'فيتامين سي ١٠٠٠ ملغ', 'Vita Plus', 60, 'pc', '60 tablets', 39.00, 52.00, 'bottle'],
        ['health-beauty', 'Sunscreen SPF 50', 'واقي شمس SPF 50', 'Pure Skin', 200, 'ml', '200 ml', 44.50, 59.00, 'bottle'],
        ['health-beauty', 'Herbal Shampoo', 'شامبو أعشاب', 'Silk Oasis', 600, 'ml', '600 ml', 18.95, 24.00, 'bottle'],
        ['baby-mother', 'Baby Wipes 3 x 72', 'مناديل أطفال مبللة ٣ × ٧٢', 'Tiny Steps', 216, 'pc', '216 wipes', 21.00, 27.50, 'box'],
        ['baby-mother', 'Infant Formula Stage 1', 'حليب أطفال المرحلة ١', 'Tiny Steps', 800, 'g', '800 g', 74.00, 89.00, 'box'],
        ['health-beauty', 'Electric Toothbrush', 'فرشاة أسنان كهربائية', 'Bright Smile', null, null, '1 unit', 129, 179, 'phone'],
    ];

    private const HOME = [
        ['home-living', '3-Seater Fabric Sofa', 'كنبة قماش ٣ مقاعد', 'Lusail Living', null, null, '210 cm', 1899, 2599, 'box'],
        ['home-living', 'Queen Bed Frame', 'سرير مقاس كوين', 'Lusail Living', null, null, '160 x 200 cm', 1299, 1699, 'box'],
        ['home-living', 'Cotton Bed Sheet Set', 'طقم شراشف قطن', 'Soft Cloud', null, null, 'Queen', 149, 219, 'box'],
        ['home-living', 'Dining Table for 6', 'طاولة طعام ٦ أشخاص', 'Lusail Living', null, null, '180 cm', 1499, 1999, 'box'],
        ['home-living', 'Floor Lamp', 'مصباح أرضي', 'Lumo', null, null, '160 cm', 199, 279, 'bottle'],
        ['home-living', 'Non-Stick Cookware Set', 'طقم أواني غير لاصقة', 'Sahra Home', null, null, '10 pcs', 349, 499, 'round'],
    ];

    private const FASHION = [
        ['fashion-apparel', 'Men Classic Thobe', 'ثوب رجالي كلاسيكي', 'Zahra', null, null, 'S – XXL', 189, 249, 'box'],
        ['fashion-apparel', 'Women Embroidered Abaya', 'عباية نسائية مطرزة', 'Zahra', null, null, '52 – 60', 349, 499, 'box'],
        ['fashion-apparel', 'Kids Sneakers', 'حذاء رياضي للأطفال', 'Stride', null, null, '28 – 35', 99, 149, 'round'],
        ['fashion-apparel', 'Leather Handbag', 'حقيبة يد جلد', 'Zahra', null, null, 'Medium', 279, 399, 'box'],
        ['fashion-apparel', 'Cotton T-Shirt 3-Pack', 'تيشيرت قطن ٣ قطع', 'Stride', null, null, 'S – XL', 79, 119, 'box'],
        ['fashion-apparel', 'Silk Scarf', 'وشاح حرير', 'Zahra', null, null, '180 cm', 89, 129, 'round'],
    ];

    /** [en, ar] */
    private const TITLES = [
        'grocery' => [['Weekend Super Savers', 'توفير نهاية الأسبوع'], ['Fresh Market Week', 'أسبوع السوق الطازج'], ['Mega Value Deals', 'عروض القيمة الكبرى'], ['Family Essentials', 'أساسيات العائلة'], ['Prices Slashed', 'أسعار مخفضة']],
        'electronics' => [['Tech Week', 'أسبوع التقنية'], ['Smart Home Deals', 'عروض المنزل الذكي'], ['Big Screen Savings', 'توفير الشاشات الكبيرة'], ['Back to School Tech', 'تقنية العودة للمدارس']],
        'pharmacy' => [['Wellness Week', 'أسبوع العافية'], ['Beauty & Care Offers', 'عروض الجمال والعناية']],
        'home' => [['Home Makeover Sale', 'تخفيضات تجديد المنزل'], ['Living Room Edit', 'تشكيلة غرفة المعيشة']],
        'fashion' => [['Season Edit', 'تشكيلة الموسم'], ['Eid Collection', 'تشكيلة العيد']],
    ];

    private DemoFlyerPainter $painter;

    /** @var array<string, int> */
    private array $categoryIds = [];

    public function run(FlyerImageProcessor $processor, PublishFlyer $publish): void
    {
        mt_srand(2026);

        $this->painter = new DemoFlyerPainter;
        $this->categoryIds = Category::query()->pluck('id', 'slug')->all();

        $doha = City::query()->where('slug', 'doha')->firstOrFail();
        $cities = City::query()->where('is_active', true)->get();
        $admin = User::query()->where('role', 'super_admin')->first();
        $today = CarbonImmutable::now(config('qdealme.timezone'))->startOfDay();

        foreach (self::RETAILERS as $index => [$slug, $en, $ar, $type, $color, $accent, $initials, $website, $whatsapp]) {
            $logoPath = "retailers/{$slug}.svg";
            Storage::disk('public')->put($logoPath, DemoFlyerPainter::logoSvg($initials, $color, $accent));

            $retailer = Retailer::query()->updateOrCreate(['slug' => $slug], [
                'name_en' => $en,
                'name_ar' => $ar,
                'type' => RetailerType::from($type),
                'logo_path' => $logoPath,
                'brand_color' => $color,
                'website_url' => $website,
                'whatsapp_number' => $whatsapp,
                'about_en' => "{$en} is a fictional demo store used to showcase QDealMe. Its flyers are generated and do not represent a real business.",
                'about_ar' => "{$ar} متجر تجريبي وهمي لعرض إمكانيات QDealMe. نشراته مُولّدة ولا تمثل نشاطاً تجارياً حقيقياً.",
                'is_active' => true,
                'sort' => $index + 1,
                'verified_at' => now(),
            ]);

            if ($retailer->flyers()->exists()) {
                continue;
            }

            $this->seedBranches($retailer, $cities);

            $source = FlyerSource::query()->create([
                'retailer_id' => $retailer->id,
                'type' => SourceType::AdminUpload,
                'license_status' => LicenseStatus::Approved,
                'permission_contact' => 'Demo data (fictional store)',
                'approved_by' => $admin?->id,
                'approved_at' => now(),
                'notes' => 'Generated demo content. Replace with real retailer permissions before launch.',
            ]);

            [$group, $catalog] = $this->catalogFor($retailer->type);

            // Two or three live flyers, one upcoming, two expired.
            $plans = [
                ['offset' => -2, 'length' => 7, 'state' => 'live'],
                ['offset' => -5, 'length' => 6 + ($index % 3), 'state' => 'live'],
                ['offset' => 3, 'length' => 7, 'state' => 'upcoming'],
                ['offset' => -20, 'length' => 7, 'state' => 'expired'],
                ['offset' => -34, 'length' => 10, 'state' => 'expired'],
            ];

            if ($index % 2 === 0) {
                $plans[] = ['offset' => 0, 'length' => 1 + ($index % 3), 'state' => 'live'];
            }

            foreach ($plans as $p => $plan) {
                $from = $today->addDays($plan['offset']);
                $until = $from->addDays($plan['length'] - 1);
                [$titleEn, $titleAr] = self::TITLES[$group][($index + $p) % count(self::TITLES[$group])];

                $flyer = Flyer::query()->create([
                    'retailer_id' => $retailer->id,
                    'flyer_source_id' => $source->id,
                    'slug' => Str::slug($titleEn),
                    'title_en' => $titleEn,
                    'title_ar' => $titleAr,
                    'valid_from' => $from,
                    'valid_until' => $until,
                    'status' => FlyerStatus::Draft,
                    'is_sponsored' => $plan['state'] === 'live' && $p === 0 && in_array($index, [0, 5], true),
                    'shop_online_url' => $website && $p !== 1 ? $website.'/shop' : null,
                    'whatsapp_order_url' => $whatsapp && ! $website ? 'https://wa.me/'.ltrim($whatsapp, '+') : null,
                ]);

                $flyer->cities()->sync($index % 3 === 0 ? $cities->pluck('id') : [$doha->id, ...$cities->where('slug', '!=', 'doha')->random(2)->pluck('id')]);
                $flyer->branches()->sync($retailer->branches()->pluck('id'));

                $pages = $this->paintFlyer($flyer, $retailer, $catalog, $color, $accent, $titleEn, $from, $until, 3 + (($index + $p) % 3));
                $processor->process($flyer);
                $this->seedOffers($flyer->refresh(), $retailer, $pages, $admin?->id);

                match ($plan['state']) {
                    'expired' => $flyer->forceFill(['status' => FlyerStatus::Expired, 'published_at' => $from, 'expired_at' => $until->addDay()])->save(),
                    default => $publish->handle($flyer, $today),
                };

                if ($plan['state'] === 'live') {
                    $flyer->forceFill(['published_at' => $from->setTime(8, 0)->addMinutes($index * 7 + $p)])->save();
                }
            }

            $this->command?->info("Seeded demo store: {$en}");
        }
    }

    /**
     * @return array{0: string, 1: array<int, array<int, mixed>>}
     */
    private function catalogFor(RetailerType $type): array
    {
        return match ($type) {
            RetailerType::Electronics => ['electronics', self::ELECTRONICS],
            RetailerType::Pharmacy => ['pharmacy', self::PHARMACY],
            RetailerType::Furniture => ['home', self::HOME],
            RetailerType::Fashion => ['fashion', self::FASHION],
            RetailerType::Hypermarket => ['grocery', [...self::GROCERY, ...array_slice(self::ELECTRONICS, 0, 8)]],
            default => ['grocery', self::GROCERY],
        };
    }

    private function seedBranches(Retailer $retailer, $cities): void
    {
        $areas = [
            ['West Bay', 'الخليج الغربي'], ['Al Sadd', 'السد'], ['The Pearl', 'اللؤلؤة'], ['Al Waab', 'الوعب'],
            ['Lusail Marina', 'مارينا لوسيل'], ['Al Gharrafa', 'الغرافة'], ['Old Airport', 'المطار القديم'],
        ];

        $cityPool = $cities->whereIn('slug', ['doha', 'al-rayyan', 'al-wakra', 'al-khor'])->values();

        $picked = (array) array_rand($areas, 3);
        shuffle($picked);

        foreach (range(0, 2) as $i) {
            $city = $i === 0 ? $cities->firstWhere('slug', 'doha') : $cityPool[mt_rand(0, $cityPool->count() - 1)];
            [$areaEn, $areaAr] = $areas[$picked[$i]];

            Branch::query()->create([
                'retailer_id' => $retailer->id,
                'city_id' => $city->id,
                'name_en' => "{$retailer->name_en} – {$areaEn}",
                'name_ar' => "{$retailer->name_ar} – {$areaAr}",
                'address_en' => "{$areaEn}, {$city->name_en}, Qatar",
                'address_ar' => "{$areaAr}، {$city->name_ar}، قطر",
                'lat' => round((float) $city->lat + mt_rand(-200, 200) / 10000, 7),
                'lng' => round((float) $city->lng + mt_rand(-200, 200) / 10000, 7),
                'phone' => '+974 4'.mt_rand(100, 999).' '.mt_rand(1000, 9999),
                'opening_hours' => ['en' => 'Daily 8:00 am – 12:00 am', 'ar' => 'يومياً ٨:٠٠ ص – ١٢:٠٠ ص'],
                'is_active' => true,
            ]);
        }
    }

    /**
     * Paint each page and remember which product sits in which tile.
     *
     * @return array<int, array{products: array<int, array<int, mixed>>, boxes: array<int, array<string, float>>}>
     */
    private function paintFlyer(Flyer $flyer, Retailer $retailer, array $catalog, string $color, string $accent, string $title, CarbonImmutable $from, CarbonImmutable $until, int $pageCount): array
    {
        $files = [];
        $pages = [];
        $validity = 'Valid '.$from->format('j M').' – '.$until->format('j M Y');

        for ($n = 1; $n <= $pageCount; $n++) {
            $products = [];
            $keys = (array) array_rand($catalog, min(6, count($catalog)));
            shuffle($keys);

            foreach ($keys as $key) {
                $item = $catalog[$key];
                // Small, deterministic variation between flyers so prices differ across stores.
                $factor = 1 + (mt_rand(-8, 6) / 100);
                $price = round($item[7] * $factor, $item[7] >= 100 ? 0 : 2);
                $old = round(max($item[8], $price * 1.08), $item[8] >= 100 ? 0 : 2);
                $item[7] = $price;
                $item[8] = mt_rand(1, 10) > 2 ? $old : null;
                $products[] = $item;
            }

            $relative = "uploads/flyers/{$flyer->id}/page-{$n}.jpg";
            $result = $this->painter->paint(
                Storage::disk('local')->path($relative),
                ['store' => $retailer->name_en, 'title' => $title, 'validity' => $validity, 'color' => $color, 'accent' => $accent, 'page' => $n, 'pages' => $pageCount],
                array_map(fn ($p) => ['name' => $p[1], 'size' => $p[6], 'price' => (float) $p[7], 'old_price' => $p[8] !== null ? (float) $p[8] : null, 'shape' => $p[9]], $products),
            );

            $files[] = $relative;
            $pages[$n] = ['products' => $products, 'boxes' => $result['boxes']];
        }

        $flyer->forceFill(['upload_files' => $files])->save();

        return $pages;
    }

    private function seedOffers(Flyer $flyer, Retailer $retailer, array $pages, ?int $adminId): void
    {
        $pageIds = $flyer->pages()->pluck('id', 'number');

        foreach ($pages as $number => $page) {
            foreach ($page['products'] as $i => $p) {
                // Most offers are verified; a few stay in the review queue to show the workflow.
                $verified = mt_rand(1, 10) <= 8;

                Offer::query()->create([
                    'flyer_id' => $flyer->id,
                    'flyer_page_id' => $pageIds[$number],
                    'retailer_id' => $retailer->id,
                    'category_id' => $this->categoryIds[$p[0]] ?? null,
                    'raw_title' => $p[1].' '.$p[6],
                    'title_en' => $p[1],
                    'title_ar' => $p[2],
                    'brand' => $p[3],
                    'size_value' => $p[4],
                    'size_unit' => $p[5],
                    'price' => $p[7],
                    'old_price' => $p[8],
                    'currency' => 'QAR',
                    'bbox' => $page['boxes'][$i],
                    'verification' => $verified ? Verification::Verified : Verification::Unverified,
                    'confidence' => round(mt_rand(82, 99) / 100, 3),
                    'verified_by' => $verified ? $adminId : null,
                    'verified_at' => $verified ? now() : null,
                ]);
            }
        }
    }
}
