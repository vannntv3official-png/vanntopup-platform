<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_google_redirect_returns_dev_simulator_when_unconfigured_locally(): void
    {
        config(['topup.google_oauth.client_id' => '', 'topup.google_oauth.client_secret' => '']);

        $this->getJson('/api/v1/auth/google/redirect')
            ->assertOk()
            ->assertJsonPath('mode', 'dev_simulator');
    }

    public function test_google_redirect_returns_real_oauth_url_when_configured(): void
    {
        config([
            'topup.google_oauth.client_id' => 'test-google-client-id',
            'topup.google_oauth.client_secret' => 'test-google-secret',
        ]);

        $res = $this->getJson('/api/v1/auth/google/redirect')
            ->assertOk()
            ->assertJsonPath('mode', 'live');

        $this->assertStringContainsString('https://accounts.google.com/o/oauth2/v2/auth', (string) $res->json('authorization_url'));
    }

    public function test_google_oauth_callback_verifies_state_exchanges_token_and_creates_user(): void
    {
        config([
            'topup.google_oauth.client_id' => 'test-google-client-id',
            'topup.google_oauth.client_secret' => 'test-google-secret',
            'topup.storefront_url' => 'http://127.0.0.1:3000',
        ]);

        Cache::put('google_oauth_state:valid-state-123', true, now()->addMinutes(5));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-998877',
                'email' => 'dara.google@gmail.com',
                'email_verified' => true,
                'name' => 'Dara Google',
                'picture' => 'https://example.com/avatar.jpg',
            ], 200),
        ]);

        $res = $this->get('/api/v1/auth/google/callback?state=valid-state-123&code=auth-code-xyz');
        $res->assertRedirect();
        $this->assertStringContainsString('http://127.0.0.1:3000/account?oauth_token=', (string) $res->headers->get('Location'));

        $user = User::query()->where('email', 'dara.google@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-sub-998877', $user->google_id);
        $this->assertSame('google', $user->auth_provider);
    }

    public function test_google_dev_login_works_locally_and_is_blocked_in_production(): void
    {
        $this->postJson('/api/v1/auth/google/dev-login')
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'auth_provider']]);

        $this->app['env'] = 'production';
        $this->postJson('/api/v1/auth/google/dev-login')->assertForbidden();
    }
}
