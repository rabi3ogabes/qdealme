<?php

namespace Tests\Feature;

use App\Enums\FlyerStatus;
use App\Enums\Verification;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_root_redirects_to_default_city_in_preferred_language(): void
    {
        // Arabic is the default when the browser prefers neither language.
        $this->get('/', ['Accept-Language' => 'fr-FR,fr;q=0.9'])->assertRedirect('/ar/qatar/doha');
        $this->get('/', ['Accept-Language' => 'ar-QA,ar;q=0.9'])->assertRedirect('/ar/qatar/doha');
        $this->get('/', ['Accept-Language' => 'en-GB,en;q=0.9'])->assertRedirect('/en/qatar/doha');
        $this->withCookie('city', 'al-wakra')->get('/en')->assertRedirect('/en/qatar/al-wakra');
    }

    public function test_unknown_locale_country_or_city_is_404(): void
    {
        $this->get('/fr/qatar/doha')->assertNotFound();
        $this->get('/ar/oman/doha')->assertNotFound();
        $this->get('/ar/qatar/atlantis')->assertNotFound();
    }

    public function test_home_renders_in_arabic_rtl_with_seo_tags(): void
    {
        $flyer = $this->makeFlyer();

        $response = $this->get($this->cityUrl());

        $response->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/qatar/doha').'">', false)
            ->assertSee('hreflang="x-default"', false)
            ->assertSee('<link rel="canonical" href="'.url('/ar/qatar/doha').'">', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"ItemList"', false)
            ->assertSee($flyer->retailer->name_ar)
            ->assertSee('عروض ذكية. بلا ضجيج.');
    }

    public function test_home_renders_in_english_ltr(): void
    {
        $flyer = $this->makeFlyer();

        $this->get($this->cityUrl('', 'en'))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee($flyer->retailer->name_en)
            ->assertSee('Smart deals. Zero noise.');
    }

    public function test_home_filters_and_sorts(): void
    {
        $electronics = $this->makeRetailer(['type' => 'electronics', 'name_en' => 'Gadget Hub']);
        $this->makeFlyer([], $electronics);
        $grocery = $this->makeRetailer(['name_en' => 'Fresh Corner']);
        $this->makeFlyer([], $grocery);

        $this->get($this->cityUrl('?type=electronics&sort=ending', 'en'))
            ->assertOk()
            ->assertSee('Gadget Hub')
            ->assertDontSee('Fresh Corner</a>', false);

        $this->get($this->cityUrl('?sort=nonsense&type=nonsense', 'en'))->assertOk();
    }

    public function test_load_more_returns_a_fragment(): void
    {
        foreach (range(1, 18) as $i) {
            $this->makeFlyer();
        }

        $this->get($this->cityUrl('?page=2&partial=1'))
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertSee('data-next-url', false);
    }

    public function test_only_flyers_valid_in_the_city_are_listed(): void
    {
        $flyer = $this->makeFlyer();

        $this->get($this->cityUrl('', 'en', 'al-khor'))->assertOk()->assertDontSee($flyer->url());
    }

    public function test_flyer_viewer_renders_pages_offers_and_canonical(): void
    {
        $flyer = $this->makeFlyer([], null, 3);
        $offer = $this->makeOffer($flyer);

        $this->get($flyer->url())
            ->assertOk()
            ->assertSee('flyerViewer(', false)
            ->assertSee('<link rel="canonical" href="'.$flyer->url().'">', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee($offer->title_ar)
            ->assertDontSee('noindex', false);

        $this->assertSame(1, $flyer->refresh()->view_count);
    }

    public function test_flyer_with_wrong_slug_redirects_permanently(): void
    {
        $flyer = $this->makeFlyer();

        $this->get($this->cityUrl("flyers/{$flyer->retailer->slug}/old-title-{$flyer->id}"))
            ->assertStatus(301)
            ->assertRedirect($flyer->url());
    }

    public function test_out_of_range_page_redirects_to_last_page(): void
    {
        $flyer = $this->makeFlyer([], null, 2);

        $this->get($flyer->url().'?page=9')->assertRedirect($flyer->url(2));
    }

    public function test_expired_flyer_is_reachable_but_gone_and_noindex(): void
    {
        $flyer = $this->makeFlyer(['status' => FlyerStatus::Expired, 'valid_from' => today()->subDays(10), 'valid_until' => today()->subDays(3)]);

        $this->get($flyer->url())
            ->assertStatus(410)
            ->assertSee('noindex, follow', false)
            ->assertSee('انتهت هذه النشرة');
    }

    public function test_draft_and_scheduled_flyers_are_not_public(): void
    {
        $draft = $this->makeFlyer(['status' => FlyerStatus::InReview]);
        $scheduled = $this->makeFlyer(['status' => FlyerStatus::Scheduled]);

        $this->get($draft->url())->assertNotFound();
        $this->get($scheduled->url())->assertNotFound();
    }

    public function test_store_pages(): void
    {
        $flyer = $this->makeFlyer();
        $expired = $this->makeFlyer(['status' => FlyerStatus::Expired], $flyer->retailer);

        $this->get($this->cityUrl('stores'))->assertOk()->assertSee($flyer->retailer->name_ar);

        $this->get($this->cityUrl('stores/'.$flyer->retailer->slug, 'en'))
            ->assertOk()
            ->assertSee($flyer->url())
            ->assertSee('"@type":"Store"', false);

        $this->get($this->cityUrl('stores/'.$flyer->retailer->slug.'/archive', 'en'))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee($expired->url());

        $this->get($this->cityUrl('stores/does-not-exist'))->assertNotFound();
    }

    public function test_static_pages_render_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (['about', 'privacy', 'terms', 'contact', 'list-your-store', 'login', 'register', 'stores', 'deals', 'search'] as $page) {
                $this->get($this->cityUrl($page, $locale))->assertOk();
            }
        }
    }

    public function test_language_switch_link_points_to_same_page(): void
    {
        $flyer = $this->makeFlyer();

        $this->get($this->cityUrl('stores/'.$flyer->retailer->slug, 'ar'))
            ->assertSee('href="'.url('/en/qatar/doha/stores/'.$flyer->retailer->slug).'"', false);
    }

    public function test_sitemaps(): void
    {
        $flyer = $this->makeFlyer();

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('sitemaps/flyers-ar-doha.xml');
        $this->get('/sitemaps/flyers-en-doha.xml')->assertOk()->assertSee(str_replace('/ar/', '/en/', $flyer->url()), false);
        $this->get('/sitemaps/stores-ar-doha.xml')->assertOk()->assertSee($flyer->retailer->slug);
    }

    public function test_security_headers_are_present(): void
    {
        $this->get($this->cityUrl())
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_sponsored_flyers_are_always_labelled(): void
    {
        $this->makeFlyer(['is_sponsored' => true]);

        $this->get($this->cityUrl('', 'en'))->assertSee('Sponsored');
        $this->get($this->cityUrl())->assertSee('مموّل');
    }

    public function test_arabic_digits_preference(): void
    {
        $this->makeFlyer();

        $this->withCookie('digits', 'arabic')->get($this->cityUrl())->assertSee('١', false);
    }

    public function test_unverified_flag_not_leaking(): void
    {
        $flyer = $this->makeFlyer();
        $this->makeOffer($flyer, ['title_ar' => 'منتج غير موثق', 'verification' => Verification::Unverified]);

        $this->get($flyer->url())->assertDontSee('منتج غير موثق');
    }
}
