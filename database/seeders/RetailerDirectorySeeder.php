<?php

namespace Database\Seeders;

use App\Enums\RetailerType;
use App\Models\Retailer;
use Illuminate\Database\Seeder;

/**
 * The main hypermarket, supermarket and electronics chains in Qatar, so staff
 * do not have to type them in. Only names and types are added: no logos and
 * no flyers. Stores start hidden and should be switched on once the store has
 * given permission and its first flyer is ready.
 *
 * Existing stores (matched by slug) are left untouched, so running this again
 * never overwrites edits made in the admin.
 */
class RetailerDirectorySeeder extends Seeder
{
    /** slug, Arabic name, English name, type, official website (only where known) */
    public const STORES = [
        ['carrefour', 'كارفور', 'Carrefour', RetailerType::Hypermarket, 'https://www.carrefourqatar.com'],
        ['lulu-hypermarket', 'لولو هايبرماركت', 'LuLu Hypermarket', RetailerType::Hypermarket, 'https://www.luluhypermarket.com'],
        ['safari-hypermarket', 'سفاري هايبرماركت', 'Safari Hypermarket', RetailerType::Hypermarket, null],
        ['grand-hypermarket', 'جراند هايبرماركت', 'Grand Hypermarket', RetailerType::Hypermarket, null],
        ['ansar-gallery', 'أنصار جاليري', 'Ansar Gallery', RetailerType::Hypermarket, null],
        ['nesto-hypermarket', 'نستو هايبرماركت', 'Nesto Hypermarket', RetailerType::Hypermarket, null],
        ['rawabi-hypermarket', 'روابي هايبرماركت', 'Rawabi Hypermarket', RetailerType::Hypermarket, null],
        ['monoprix', 'مونوبري', 'Monoprix', RetailerType::Supermarket, 'https://www.monoprix.qa'],
        ['al-meera', 'الميرة', 'Al Meera', RetailerType::Supermarket, 'https://www.almeera.com.qa'],
        ['spar', 'سبار', 'SPAR', RetailerType::Supermarket, null],
        ['family-food-centre', 'فاميلي فود سنتر', 'Family Food Centre', RetailerType::Supermarket, null],
        ['megamart', 'ميجامارت', 'MegaMart', RetailerType::Supermarket, null],
        ['jumbo-electronics', 'جمبو للإلكترونيات', 'Jumbo Electronics', RetailerType::Electronics, null],
        ['jarir-bookstore', 'مكتبة جرير', 'Jarir Bookstore', RetailerType::Electronics, 'https://www.jarir.com'],
        ['virgin-megastore', 'فيرجن ميغاستور', 'Virgin Megastore', RetailerType::Electronics, 'https://www.virginmegastore.qa'],
        ['emax', 'إيماكس', 'Emax', RetailerType::Electronics, null],
        // Added later: new stores go at the end so existing sort positions never collide.
        ['paris-hypermarket', 'باريس هايبرماركت', 'Paris Hypermarket', RetailerType::Hypermarket, null],
    ];

    public function run(): void
    {
        foreach (self::STORES as $i => [$slug, $nameAr, $nameEn, $type, $website]) {
            Retailer::withTrashed()->firstOrCreate(['slug' => $slug], [
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'type' => $type,
                'website_url' => $website,
                'is_active' => false,
                'sort' => 100 + $i,
            ]);
        }
    }
}
