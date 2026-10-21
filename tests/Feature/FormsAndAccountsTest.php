<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\Report;
use App\Models\User;
use Tests\TestCase;

class FormsAndAccountsTest extends TestCase
{
    public function test_contact_form_stores_message(): void
    {
        $this->post($this->cityUrl('contact', 'en'), [
            'name' => 'Sara',
            'email' => 'sara@example.test',
            'message' => 'The price for rice on page 2 is wrong.',
        ])->assertRedirect($this->cityUrl('contact', 'en'))->assertSessionHas('status');

        $this->assertDatabaseHas('contact_messages', ['email' => 'sara@example.test', 'locale' => 'en']);
    }

    public function test_contact_form_validates_and_blocks_bots(): void
    {
        $this->post($this->cityUrl('contact'), ['name' => '', 'email' => 'nope', 'message' => 'short'])
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->post($this->cityUrl('contact'), [
            'name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Buy cheap things now please', 'website' => 'http://spam.test',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_contact_form_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post($this->cityUrl('contact'), ['name' => 'A', 'email' => "a{$i}@example.test", 'message' => 'Hello there, a message.']);
        }

        $this->post($this->cityUrl('contact'), ['name' => 'A', 'email' => 'a6@example.test', 'message' => 'Hello there, a message.'])
            ->assertStatus(429);
    }

    public function test_list_your_store_creates_a_lead(): void
    {
        $this->post($this->cityUrl('list-your-store'), [
            'company_name' => 'Bayt Bakery',
            'contact_name' => 'Ahmed',
            'email' => 'ahmed@example.test',
            'city_id' => $this->doha()->id,
            'retailer_type' => 'supermarket',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame('Bayt Bakery', Lead::query()->first()->company_name);
    }

    public function test_reporting_a_flyer(): void
    {
        $flyer = $this->makeFlyer();

        $this->from($flyer->url())->post($this->cityUrl('report/'.$flyer->id), ['reason' => 'wrong_price', 'details' => 'Rice price differs'])
            ->assertRedirect($flyer->url());

        $report = Report::query()->first();
        $this->assertSame($flyer->id, $report->flyer_id);
        $this->assertSame(64, strlen($report->ip_hash));

        $this->post($this->cityUrl('report/'.$flyer->id), ['reason' => 'nonsense'])->assertSessionHasErrors('reason');
    }

    public function test_registration_login_and_logout(): void
    {
        $this->post($this->cityUrl('register', 'en'), [
            'name' => 'Noor',
            'email' => 'noor@example.test',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ])->assertRedirect($this->cityUrl('', 'en'));

        $user = User::query()->where('email', 'noor@example.test')->first();
        $this->assertSame(UserRole::Shopper, $user->role);
        $this->assertSame('en', $user->locale);
        $this->assertAuthenticatedAs($user);

        $this->post($this->cityUrl('logout'))->assertRedirect();
        $this->assertGuest();

        $this->post($this->cityUrl('login'), ['email' => 'noor@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post($this->cityUrl('login'), ['email' => 'noor@example.test', 'password' => 'a-long-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_guests_are_sent_to_localized_login(): void
    {
        $this->get($this->cityUrl('account', 'en'))->assertRedirect($this->cityUrl('login', 'en'));
        $this->get($this->cityUrl('saved'))->assertRedirect($this->cityUrl('login'));
    }

    public function test_follow_and_save_toggle(): void
    {
        $user = $this->makeUser();
        $flyer = $this->makeFlyer();

        $this->actingAs($user)->postJson($this->cityUrl('follow/'.$flyer->retailer->slug))->assertJson(['following' => true]);
        $this->actingAs($user)->postJson($this->cityUrl('favorite/'.$flyer->id))->assertJson(['saved' => true]);

        $this->actingAs($user)->get($this->cityUrl('saved', 'en'))->assertOk()->assertSee($flyer->url());

        $this->actingAs($user)->postJson($this->cityUrl('follow/'.$flyer->retailer->slug))->assertJson(['following' => false]);
        $this->assertSame(0, $user->follows()->count());
    }

    public function test_guest_toggle_requires_login(): void
    {
        $flyer = $this->makeFlyer();

        $this->postJson($this->cityUrl('favorite/'.$flyer->id))->assertUnauthorized();
    }

    public function test_account_update_and_delete(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->patch($this->cityUrl('account'), [
            'name' => 'New Name', 'locale' => 'en', 'digits' => 'arabic', 'city_id' => $this->doha()->id,
        ])->assertRedirect($this->cityUrl('account', 'en'));

        $this->assertSame('arabic', $user->refresh()->digits);

        $this->actingAs($user)->delete($this->cityUrl('account'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($user)->delete($this->cityUrl('account'), ['password' => 'password-123'])->assertRedirect();

        $this->assertModelMissing($user);
        $this->assertGuest();
    }
}
