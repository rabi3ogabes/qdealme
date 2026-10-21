<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    public function test_guests_are_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_shoppers_cannot_enter_the_admin_panel(): void
    {
        $this->actingAs($this->makeUser(UserRole::Shopper))->get('/admin')->assertForbidden();
    }

    public function test_staff_can_open_every_admin_screen(): void
    {
        $flyer = $this->makeFlyer();
        $this->makeOffer($flyer);
        $admin = $this->makeUser(UserRole::SuperAdmin);

        foreach (['/admin', '/admin/flyers', '/admin/flyers/create', "/admin/flyers/{$flyer->id}/edit", '/admin/offers', '/admin/retailers', "/admin/retailers/{$flyer->retailer->slug}/edit", '/admin/flyer-sources', '/admin/categories', '/admin/cities', '/admin/leads', '/admin/contact-messages', '/admin/reports', '/admin/users'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_only_super_admins_manage_users(): void
    {
        $reviewer = $this->makeUser(UserRole::Reviewer);

        $this->actingAs($reviewer)->get('/admin/flyers')->assertOk();
        $this->actingAs($reviewer)->get('/admin/users')->assertForbidden();
    }

    public function test_two_factor_setup_is_required_when_enabled(): void
    {
        config(['qdealme.admin_require_mfa' => true]);
        $this->refreshApplicationWithMfa();

        $response = $this->actingAs($this->makeUser(UserRole::ContentAdmin))->get('/admin');

        $response->assertRedirect();
        $this->assertStringContainsString('multi-factor', $response->headers->get('Location'));
    }

    private function refreshApplicationWithMfa(): void
    {
        $_ENV['ADMIN_REQUIRE_MFA'] = 'true';
        putenv('ADMIN_REQUIRE_MFA=true');
        $this->refreshApplication();
        $this->withoutVite();
        putenv('ADMIN_REQUIRE_MFA=false');
        $_ENV['ADMIN_REQUIRE_MFA'] = 'false';
    }
}
