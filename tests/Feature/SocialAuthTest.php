<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github' => [
                'client_id' => 'github_client_id_test',
                'client_secret' => 'github_client_secret_test',
                'redirect' => 'http://localhost/auth/github/callback',
            ],
            'services.google' => [
                'client_id' => 'google_client_id_test',
                'client_secret' => 'google_client_secret_test',
                'redirect' => 'http://localhost/auth/google/callback',
            ],
        ]);
    }

    public function test_unknown_provider_results_in_spa_fallback(): void
    {
        $response = $this->get('/auth/facebook/redirect');

        $response->assertStatus(200);
    }

    public function test_unconfigured_provider_redirects_to_login(): void
    {
        config(['services.github.client_id' => null]);

        $response = $this->get('/auth/github/redirect');

        $response->assertRedirect('/login?oauth_error=social_not_configured');
    }

    public function test_github_redirect_redirects_to_oauth_provider(): void
    {
        $response = $this->get('/auth/github/redirect');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('github.com/login/oauth/authorize', $location);
        $this->assertStringContainsString('client_id=github_client_id_test', $location);
        $this->assertStringContainsString('redirect_uri=', $location);
    }

    public function test_google_redirect_redirects_to_oauth_provider(): void
    {
        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('accounts.google.com/o/oauth2/auth', $location);
        $this->assertStringContainsString('client_id=google_client_id_test', $location);
        $this->assertStringContainsString('redirect_uri=', $location);
    }

    public function test_callback_creates_new_user_and_redirects_with_token(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('github_12345');
        $socialiteUser->shouldReceive('getName')->andReturn('John Developer');
        $socialiteUser->shouldReceive('getNickname')->andReturn('johndev');
        $socialiteUser->shouldReceive('getEmail')->andReturn('john@example.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://avatars.githubusercontent.com/u/12345');

        $providerMock = Mockery::mock(SocialiteProvider::class);
        $providerMock->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($providerMock);

        $response = $this->get('/auth/github/callback?code=valid_test_code');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/oauth/callback?token=', $location);
        $this->assertStringContainsString('email=john%40example.com', $location);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Developer',
            'provider' => 'github',
            'provider_id' => 'github_12345',
            'provider_avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
            'role' => User::ROLE_USER,
            'is_active' => true,
        ]);
    }

    public function test_callback_logs_in_existing_user_and_updates_provider(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'provider' => null,
            'provider_id' => null,
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google_98765');
        $socialiteUser->shouldReceive('getName')->andReturn('Existing User');
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);
        $socialiteUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        $providerMock = Mockery::mock(SocialiteProvider::class);
        $providerMock->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($providerMock);

        $response = $this->get('/auth/google/callback?code=valid_test_code');

        $response->assertRedirect();
        $this->assertStringContainsString('/oauth/callback?token=', $response->headers->get('Location'));

        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'email' => 'existing@example.com',
            'provider' => 'google',
            'provider_id' => 'google_98765',
            'provider_avatar_url' => 'https://lh3.googleusercontent.com/avatar.jpg',
        ]);
    }

    public function test_callback_deactivated_user_redirects_with_error(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('github_000');
        $socialiteUser->shouldReceive('getName')->andReturn('Inactive User');
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);
        $socialiteUser->shouldReceive('getEmail')->andReturn('inactive@example.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);

        $providerMock = Mockery::mock(SocialiteProvider::class);
        $providerMock->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($providerMock);

        $response = $this->get('/auth/github/callback?code=valid_test_code');

        $response->assertRedirect('/login?oauth_error=account_deactivated');
    }

    public function test_callback_missing_email_redirects_with_error(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('github_no_email');
        $socialiteUser->shouldReceive('getName')->andReturn('No Email User');
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);
        $socialiteUser->shouldReceive('getEmail')->andReturn(null);

        $providerMock = Mockery::mock(SocialiteProvider::class);
        $providerMock->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($providerMock);

        $response = $this->get('/auth/github/callback?code=valid_test_code');

        $response->assertRedirect('/login?oauth_error=missing_email');
    }

    public function test_callback_with_oauth_error_param_redirects_with_error(): void
    {
        $response = $this->get('/auth/github/callback?error=access_denied');

        $response->assertRedirect('/login?oauth_error=oauth_failed');
    }
}
