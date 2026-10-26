<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Turnstile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EdgeTest extends TestCase
{
    public function test_dynamic_pages_are_never_cached_by_the_edge(): void
    {
        $this->seedBank();
        $this->learner();

        $response = $this->get('/');

        $response->assertHeader('Cloudflare-CDN-Cache-Control', 'no-store');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_turnstile_is_skipped_when_not_configured(): void
    {
        $user = User::factory()->create();

        $this->post(route('staff.login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('staff.items.index'));
    }

    public function test_turnstile_blocks_sign_in_when_the_check_fails(): void
    {
        config(['examprep.turnstile' => ['site_key' => 'site', 'secret_key' => 'secret']]);
        Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => false])]);
        $user = User::factory()->create();

        $this->post(route('staff.login'), [
            'email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'token',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_turnstile_allows_sign_in_when_the_check_passes(): void
    {
        config(['examprep.turnstile' => ['site_key' => 'site', 'secret_key' => 'secret']]);
        Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);
        $user = User::factory()->create();

        $this->post(route('staff.login'), [
            'email' => $user->email, 'password' => 'password', 'cf-turnstile-response' => 'token',
        ])->assertRedirect(route('staff.items.index'));

        $this->assertAuthenticatedAs($user);
    }
}
