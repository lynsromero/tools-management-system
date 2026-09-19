<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SocialAuthController extends Controller
{
    protected array $supportedProviders = ['github', 'google'];

    public function redirect(string $provider): Response
    {
        abort_unless(in_array($provider, $this->supportedProviders, true), 404);

        if (! $this->isConfigured($provider)) {
            return redirect('/login?oauth_error=social_not_configured');
        }

        try {
            return Socialite::driver($provider)->redirect();
        } catch (Throwable) {
            return redirect('/login?oauth_error=social_not_configured');
        }
    }

    public function callback(string $provider, Request $request): Response
    {
        abort_unless(in_array($provider, $this->supportedProviders, true), 404);

        if (! $this->isConfigured($provider)) {
            return redirect('/login?oauth_error=social_not_configured');
        }

        if ($request->has('error') || ! $request->has('code')) {
            return redirect('/login?oauth_error=oauth_failed');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException) {
            return redirect('/login?oauth_error=invalid_state');
        } catch (Throwable) {
            return redirect('/login?oauth_error=oauth_failed');
        }

        $email = $socialUser->getEmail();

        if (empty($email)) {
            return redirect('/login?oauth_error=missing_email');
        }

        $providerId = (string) $socialUser->getId();
        $name = $socialUser->getName()
            ?? $socialUser->getNickname()
            ?? ($provider === 'github' ? 'GitHub User' : 'Google User');
        $avatarUrl = $socialUser->getAvatar();

        $user = User::where('provider', $provider)->where('provider_id', $providerId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $user->update([
                'provider' => $provider,
                'provider_id' => $providerId,
                'provider_avatar_url' => $avatarUrl,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'role' => User::ROLE_USER,
                'is_active' => true,
                'provider' => $provider,
                'provider_id' => $providerId,
                'provider_avatar_url' => $avatarUrl,
                'email_verified_at' => now(),
                'referral_code' => $this->uniqueReferralCode(),
            ]);
        }

        if (! $user->is_active) {
            return redirect('/login?oauth_error=account_deactivated');
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return redirect(config('app.url').'/oauth/callback?token='.$token.'&email='.urlencode($user->email));
    }

    protected function isConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        return ! empty($config['client_id'])
            && ! empty($config['client_secret']);
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
