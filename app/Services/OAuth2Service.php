<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use RuntimeException;

class OAuth2Service
{
    public function redirectUrl(string $provider): string
    {
        $this->ensureConfigured($provider);

        return Socialite::driver($provider)->redirect()->getTargetUrl();
    }

    public function user(string $provider, ?string $code = null): array
    {
        $this->ensureConfigured($provider);

        $socialUser = Socialite::driver($provider)->user();

        return [
            'id' => (string) $socialUser->getId(),
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'email' => $socialUser->getEmail(),
            'avatar_url' => $socialUser->getAvatar(),
        ];
    }

    private function ensureConfigured(string $provider): void
    {
        $config = config("services.{$provider}");

        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new RuntimeException("OAuth provider {$provider} is not configured.");
        }
    }
}
