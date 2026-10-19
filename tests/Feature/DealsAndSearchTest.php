<?php

namespace Tests\Feature;

use App\Enums\FlyerStatus;
use App\Enums\Verification;
use App\Models\Category;
use Tests\TestCase;

class DealsAndSearchTest extends TestCase
{
    public function test_only_verified_offers_on_live_flyers_appear_in_deals(): void
    {
        $flyer = $this->makeFlyer();
        $this->makeOffer($flyer, ['title_en' => 'Verified Rice']);
        $this->makeOffer($flyer, ['title_en' => 'Unverified Oil', 'verification' => Verification::Unverified]);
        $this->makeOffer($flyer, ['title_en' => 'Rejected Milk', 'verification' => Verification::Rejected]);

        $expired = $this->makeFlyer(['status' => FlyerStatus::Expired]);
        $this->makeOffer($expired, ['title_en' => 'Old Sugar']);

        $this->get($this->cityUrl('deals', 'en'))
            ->assertOk()
            ->assertSee('Verified Rice')
            ->assertDontSee('Unverified Oil')
            ->assertDontSee('Rejected Milk')
            ->assertDontSee('Old Sugar');
    }

    public function test_unverified_offer_has_no_product_page(): void
    {
        $offer = $this->makeOffer($this->makeFlyer(), ['verification' => Verification::Unverified]);

        $this->get($this->cityUrl('p/'.$offer->routeSegment()))->assertNotFound();
    }

    public function test_product_page_has_clean_bilingual_title_and_json_ld(): void
    {
        $offer = $this->makeOffer($this->makeFlyer(), ['raw_title' => 'BASMTI RCE 5KG!!', 'brand' => 'Golden Grain']);

        $this->get($this->cityUrl('p/'.$offer->routeSegment(), 'en'))
            ->assertOk()
            ->assertSee('<title>Basmati Rice 5 kg at', false)
            ->assertDontSee('BASMTI RCE')
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"priceValidUntil"', false)
            ->assertSee('QAR 6.59 / kg');

        $this->get($this->cityUrl('p/'.$offer->routeSegment(), 'ar'))->assertOk()->assertSee('أرز بسمتي ٥ كغ');
    }

    public function test_product_with_wrong_slug_redirects_and_expired_is_gone(): void
    {
        $offer = $this->makeOffer($this->makeFlyer());

        $this->get($this->cityUrl('p/wrong-'.$offer->id))->assertStatus(301);

        $expiredOffer = $this->makeOffer($this->makeFlyer(['status' => FlyerStatus::Expired]));
        $this->get($this->cityUrl('p/'.$expiredOffer->routeSegment()))->assertStatus(410)->assertSee('noindex', false);
    }

    public function test_category_filter_includes_subcategories(): void
    {
        $flyer = $this->makeFlyer();
        $mobiles = Category::query()->where('slug', 'mobiles')->first();
        $this->makeOffer($flyer, ['title_en' => 'Nova Phone', 'category_id' => $mobiles->id]);
        $this->makeOffer($flyer, ['title_en' => 'Plain Rice', 'category_id' => Category::query()->where('slug', 'pantry-rice')->value('id')]);

        $this->get($this->cityUrl('deals/electronics', 'en'))
            ->assertOk()
            ->assertSee('Nova Phone')
            ->assertDontSee('Plain Rice');
    }

    public function test_price_filters_and_sorting(): void
    {
        $flyer = $this->makeFlyer();
        $this->makeOffer($flyer, ['title_en' => 'Cheap Item', 'price' => 5, 'old_price' => 6]);
        $this->makeOffer($flyer, ['title_en' => 'Pricey Item', 'price' => 500, 'old_price' => 900]);

        $this->get($this->cityUrl('deals?min=100', 'en'))->assertSee('Pricey Item')->assertDontSee('Cheap Item');
        $this->get($this->cityUrl('deals?max=10', 'en'))->assertSee('Cheap Item')->assertDontSee('Pricey Item');

        foreach (['best', 'price_asc', 'price_desc', 'saving', 'newest', 'ending', 'invalid'] as $sort) {
            $this->get($this->cityUrl('deals?sort='.$sort, 'en'))->assertOk();
        }

        $this->get($this->cityUrl('deals?sort=price_asc', 'en'))->assertSeeInOrder(['Cheap Item', 'Pricey Item']);
    }

    public function test_arabic_search_matches_spelling_variants_and_english_synonyms(): void
    {
        $flyer = $this->makeFlyer();
        $this->makeOffer($flyer, ['title_en' => 'iPhone 17 Pro', 'title_ar' => 'آيفون ١٧ برو']);

        // Typed without hamza, matches the hamza spelling.
        $this->get($this->cityUrl('search?q='.urlencode('ايفون')))->assertOk()->assertSee('آيفون ١٧ برو');

        // Arabic query matches the English title via synonyms.
        $this->get($this->cityUrl('search?q='.urlencode('ايفون'), 'en'))->assertSee('iPhone 17 Pro');

        // English query.
        $this->get($this->cityUrl('search?q=iphone', 'en'))->assertSee('iPhone 17 Pro');
    }

    public function test_search_finds_stores_and_flyers(): void
    {
        $flyer = $this->makeFlyer(['title_en' => 'Ramadan Specials'], $this->makeRetailer(['name_en' => 'Pearl Market', 'name_ar' => 'سوق اللؤلؤة']));

        $this->get($this->cityUrl('search?q=pearl', 'en'))->assertSee('Pearl Market')->assertSee($flyer->url());
        $this->get($this->cityUrl('search?q='.urlencode('اللؤلؤه')))->assertSee('سوق اللؤلؤة');
    }

    public function test_suggest_endpoint_returns_grouped_json(): void
    {
        $flyer = $this->makeFlyer([], $this->makeRetailer(['name_en' => 'Pearl Market']));
        $this->makeOffer($flyer, ['title_en' => 'Pearl Rice']);

        $this->getJson($this->cityUrl('search/suggest?q=pearl', 'en'))
            ->assertOk()
            ->assertJsonStructure(['retailers', 'flyers', 'offers', 'categories', 'more'])
            ->assertJsonPath('retailers.0.title', 'Pearl Market');

        $this->getJson($this->cityUrl('search/suggest?q=p'))->assertOk()->assertJsonPath('retailers', []);
    }

    public function test_search_input_is_escaped(): void
    {
        $this->get($this->cityUrl('search?q='.urlencode('<script>alert(1)</script>'), 'en'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_like_wildcards_are_escaped(): void
    {
        $this->makeOffer($this->makeFlyer(), ['title_en' => 'Plain Product']);

        $this->get($this->cityUrl('search?q='.urlencode('%%'), 'en'))->assertOk()->assertDontSee('Plain Product');
    }
}
