<?php

namespace Tests\Feature;

use Tests\TestCase;

class OfferPricingTest extends TestCase
{
    public function test_unit_price_normalizes_grams_and_millilitres(): void
    {
        $flyer = $this->makeFlyer();

        $grams = $this->makeOffer($flyer, ['price' => 5.00, 'old_price' => null, 'size_value' => 500, 'size_unit' => 'g']);
        $this->assertEquals(10.0, (float) $grams->unit_price);
        $this->assertSame('kg', $grams->unit);

        $ml = $this->makeOffer($flyer, ['price' => 3.00, 'old_price' => null, 'size_value' => 1500, 'size_unit' => 'ml']);
        $this->assertEquals(2.0, (float) $ml->unit_price);
        $this->assertSame('l', $ml->unit);

        $pieces = $this->makeOffer($flyer, ['price' => 18.00, 'old_price' => null, 'size_value' => 30, 'size_unit' => 'pc']);
        $this->assertEquals(0.6, (float) $pieces->unit_price);
    }

    public function test_saving_is_only_reported_when_old_price_is_higher(): void
    {
        $flyer = $this->makeFlyer();

        $offer = $this->makeOffer($flyer, ['price' => 75, 'old_price' => 100]);
        $this->assertSame(25.0, $offer->savingAmount());
        $this->assertSame(25, $offer->savingPercent());

        $noSaving = $this->makeOffer($flyer, ['price' => 100, 'old_price' => 90]);
        $this->assertNull($noSaving->savingAmount());
        $this->assertNull($noSaving->savingPercent());
    }

    public function test_no_unit_price_without_size(): void
    {
        $offer = $this->makeOffer($this->makeFlyer(), ['size_value' => null, 'size_unit' => null]);

        $this->assertNull($offer->unit_price);
    }
}
