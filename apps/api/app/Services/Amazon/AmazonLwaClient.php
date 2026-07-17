<?php

namespace App\Services\Amazon;

use App\Models\ChannelAccount;
use App\Services\Amazon\Exceptions\AmazonSpApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class AmazonLwaClient
{
    private const TOKEN_URL = 'https://api.amazon.com/auth/o2/token';

    public function __construct(private readonly AmazonConfiguration $configuration) {}

    public function exchangeAuthorizationCode(string $code): array
    {
        $this->configuration->assertConfigured();

        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.amazon.redirect_uri'),
        ]);
    }

    public function accessToken(ChannelAccount $account): string
    {
        if (blank($account->refresh_token)) {
            throw new AmazonSpApiException('This Amazon account has no refresh token. Reconnect the seller account.');
        }

        $cacheKey = 'amazon_lwa_access_token:'.$account->getKey();

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($account): string {
            $token = $this->tokenRequest([
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);

            $account->forceFill(['token_last_refreshed_at' => now()])->saveQuietly();

            return $token['access_token'];
        });
    }

    private function tokenRequest(array $payload): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 400)
            ->post(self::TOKEN_URL, [
                ...$payload,
                'client_id' => config('services.amazon.lwa_client_id'),
                'client_secret' => config('services.amazon.lwa_client_secret'),
            ]);

        if ($response->failed()) {
            throw new AmazonSpApiException(
                $response->json('error_description') ?: $response->json('error') ?: 'Amazon LWA token exchange failed.',
                $response->status(),
                errors: $response->json() ?? [],
            );
        }

        $data = $response->json();

        if (! is_array($data) || blank($data['access_token'] ?? null)) {
            throw new AmazonSpApiException('Amazon LWA returned an invalid token response.');
        }

        return $data;
    }
}
