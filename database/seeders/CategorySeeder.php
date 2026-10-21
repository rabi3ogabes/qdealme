<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The initial bilingual category tree.
     */
    public const TREE = [
        ['electronics', 'إلكترونيات', 'Electronics', [
            ['mobiles', 'هواتف', 'Mobiles'],
            ['tvs', 'تلفزيونات', 'TVs'],
            ['computers-laptops', 'كمبيوتر ولابتوب', 'Computers & Laptops'],
            ['tablets', 'أجهزة لوحية', 'Tablets'],
            ['smart-watches', 'ساعات ذكية', 'Smart Watches'],
            ['monitors-projectors', 'شاشات وأجهزة عرض', 'Monitors & Projectors'],
            ['printers', 'طابعات', 'Printers'],
            ['cameras', 'كاميرات', 'Cameras'],
            ['gaming', 'ألعاب', 'Gaming'],
            ['accessories', 'إكسسوارات', 'Accessories'],
            ['large-appliances', 'أجهزة كبيرة', 'Large Appliances'],
            ['small-appliances', 'أجهزة صغيرة', 'Small Appliances'],
            ['kitchen-appliances', 'أجهزة المطبخ', 'Kitchen Appliances'],
        ]],
        ['grocery', 'بقالة', 'Grocery', [
            ['fruits-vegetables', 'فواكه وخضروات', 'Fruits & Vegetables'],
            ['dairy-eggs', 'ألبان وبيض', 'Dairy & Eggs'],
            ['meat-poultry-fish', 'لحوم ودواجن وأسماك', 'Meat, Poultry & Fish'],
            ['frozen', 'مجمدات', 'Frozen'],
            ['beverages', 'مشروبات', 'Beverages'],
            ['bakery-confectionery', 'مخبوزات وحلويات', 'Bakery & Confectionery'],
            ['pantry-rice', 'أرز ومؤن', 'Pantry & Rice'],
            ['snacks', 'وجبات خفيفة', 'Snacks'],
        ]],
        ['health-beauty', 'صحة وجمال', 'Health & Beauty', []],
        ['baby-mother', 'الأم والطفل', 'Baby & Mother', []],
        ['laundry-cleaning', 'غسيل وتنظيف', 'Laundry & Cleaning', []],
        ['tissue-disposables', 'مناديل ومستلزمات', 'Tissue & Disposables', []],
        ['home-living', 'المنزل والمعيشة', 'Home & Living', []],
        ['fashion-apparel', 'أزياء وملابس', 'Fashion & Apparel', []],
    ];

    public function run(): void
    {
        foreach (self::TREE as $i => [$slug, $ar, $en, $children]) {
            $root = Category::query()->updateOrCreate(['slug' => $slug], [
                'parent_id' => null, 'name_ar' => $ar, 'name_en' => $en, 'sort' => $i + 1,
            ]);

            foreach ($children as $j => [$childSlug, $childAr, $childEn]) {
                Category::query()->updateOrCreate(['slug' => $childSlug], [
                    'parent_id' => $root->id, 'name_ar' => $childAr, 'name_en' => $childEn, 'sort' => $j + 1,
                ]);
            }
        }
    }
}
