<?php

namespace App\Services\Amazon;

use App\Models\ChannelAccount;
use App\Services\Amazon\Exceptions\AmazonSpApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class AmazonSpApiClient
{
    public function __construct(
        private readonly AmazonConfiguration $configuration,
        private readonly AmazonLwaClient $lwa,
    ) {}

    public function get(ChannelAccount $account, string $path, array $query = [], ?string $region = null): array
    {
        return $this->send($account, 'GET', $path, $query, [], $region);
    }

    public function post(ChannelAccount $account, string $path, array $query = [], array $json = [], ?string $region = null): array
    {
        return $this->send($account, 'POST', $path, $query, $json, $region);
    }

    public function put(ChannelAccount $account, string $path, array $query = [], array $json = [], ?string $region = null): array
    {
        return $this->send($account, 'PUT', $path, $query, $json, $region);
    }

    public function patch(ChannelAccount $account, string $path, array $query = [], array $json = [], ?string $region = null): array
    {
        return $this->send($account, 'PATCH', $path, $query, $json, $region);
    }

    private function send(ChannelAccount $account, string $method, string $path, array $query = [], array $json = [], ?string $region = null): array
    {
        $this->configuration->assertConfigured();
        $sandbox = (bool) ($account->metadata['sandbox'] ?? config('services.amazon.sandbox'));
        $targetRegion = $region ?? $account->region;
        $url = rtrim($this->configuration->endpoint($targetRegion, $sandbox), '/').'/'.ltrim($path, '/');

        $request = $this->request($account);
        $options = ['query' => $this->normalizeQuery($query)];

        if ($json !== []) {
            $options['json'] = $json;
        }

        $response = $request->send($method, $url, $options);

        if ($response->failed()) {
            $this->throwForResponse($response);
        }

        return $response->json() ?? [];
    }

    private function request(ChannelAccount $account): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.amazon.timeout', 30))
            ->connectTimeout(10)
            ->retry(
                (int) config('services.amazon.retry_times', 3),
                fn (int $attempt): int => min(5000, 400 * (2 ** ($attempt - 1))),
                fn (Throwable $exception, PendingRequest $request): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response?->status() === 429 || ($exception->response?->status() ?? 0) >= 500)),
                throw: false,
            )
            ->withHeaders([
                'x-amz-access-token' => $this->lwa->accessToken($account),
                'x-amz-date' => now('UTC')->format('Ymd\THis\Z'),
                'user-agent' => config('services.amazon.user_agent', 'OopSeller/1.0 (Language=PHP; Platform=Laravel)'),
            ]);
    }

    private function normalizeQuery(array $query): array
    {
        return collect($query)
            ->reject(fn ($value) => $value === null || $value === '')
            ->map(fn ($value) => is_array($value) ? implode(',', $value) : $value)
            ->all();
    }

    private function throwForResponse(Response $response): never
    {
        $payload = $response->json() ?? [];
        $errors = $payload['errors'] ?? [];
        $message = data_get($errors, '0.message')
            ?: data_get($payload, 'message')
            ?: "Amazon SP-API request failed with HTTP {$response->status()}.";

        throw new AmazonSpApiException(
            Str::limit((string) $message, 1000),
            $response->status(),
            $response->header('x-amzn-RequestId'),
            is_array($errors) ? $errors : [],
        );
    }
}
