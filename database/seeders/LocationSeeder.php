<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $qatar = Country::query()->updateOrCreate(['code' => 'QA'], [
            'slug' => 'qatar',
            'name_ar' => 'قطر',
            'name_en' => 'Qatar',
            'currency' => 'QAR',
            'timezone' => 'Asia/Qatar',
            'is_active' => true,
            'sort' => 1,
        ]);

        $cities = [
            ['doha', 'الدوحة', 'Doha', 25.2854, 51.5310,
                'الدوحة هي قلب التسوق في قطر، حيث تجتمع أكبر الهايبرماركت ومتاجر الإلكترونيات. تصدر معظم النشرات الجديدة يومي الأربعاء والخميس، وتمتد عروض نهاية الأسبوع عادةً حتى السبت.',
                'Doha is the heart of shopping in Qatar, home to the largest hypermarkets and electronics stores. Most new flyers land on Wednesdays and Thursdays, and weekend deals usually run until Saturday.'],
            ['al-rayyan', 'الريان', 'Al Rayyan', 25.2919, 51.4244, null, null],
            ['al-wakra', 'الوكرة', 'Al Wakra', 25.1659, 51.6038, null, null],
            ['al-khor', 'الخور', 'Al Khor', 25.6804, 51.4969, null, null],
            ['umm-salal', 'أم صلال', 'Umm Salal', 25.4150, 51.4044, null, null],
            ['al-daayen', 'الضعاين', 'Al Daayen', 25.5784, 51.4823, null, null],
            ['al-shamal', 'الشمال', 'Al Shamal', 26.1293, 51.2009, null, null],
            ['al-shahaniya', 'الشحانية', 'Al Shahaniya', 25.3707, 51.2219, null, null],
        ];

        foreach ($cities as $i => [$slug, $ar, $en, $lat, $lng, $introAr, $introEn]) {
            City::query()->updateOrCreate(
                ['country_id' => $qatar->id, 'slug' => $slug],
                ['name_ar' => $ar, 'name_en' => $en, 'lat' => $lat, 'lng' => $lng, 'intro_ar' => $introAr, 'intro_en' => $introEn, 'is_active' => true, 'sort' => $i + 1],
            );
        }
    }
}
