<?php

namespace Tests\Unit;

use App\Support\Arabic;
use Tests\TestCase;

class ArabicTest extends TestCase
{
    protected bool $seedLocations = false;

    public function test_normalizes_hamza_ta_marbuta_and_diacritics(): void
    {
        $this->assertSame(Arabic::normalize('أيفون'), Arabic::normalize('ايفون'));
        $this->assertSame(Arabic::normalize('إيفون'), Arabic::normalize('آيفون'));
        $this->assertSame('شاشه', Arabic::normalize('شاشة'));
        $this->assertSame('ارز', Arabic::normalize('أَرُزّ'));
        $this->assertSame('123', Arabic::normalize('١٢٣'));
    }

    public function test_expands_synonyms_across_languages(): void
    {
        $terms = Arabic::expand(Arabic::normalize('ايفون'));

        $this->assertContains('iphone', $terms);
    }

    public function test_converts_digits(): void
    {
        $this->assertSame('٢٫٥', str_replace('.', '٫', Arabic::toArabicDigits('2.5')));
        $this->assertSame('١٠٠', Arabic::toArabicDigits('100'));
    }
}
