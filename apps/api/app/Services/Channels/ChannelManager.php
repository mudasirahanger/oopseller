<?php

namespace App\Services\Channels;

use App\Enums\Platform;
use App\Services\Channels\Contracts\ChannelProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ChannelManager
{
    /** @var array<string, class-string<ChannelProvider>> */
    private const PROVIDERS = [
        Platform::Amazon->value => AmazonChannelProvider::class,
        // Register new platform adapters here as they are implemented, e.g.
        // Platform::Flipkart->value => FlipkartChannelProvider::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function has(Platform|string $platform): bool
    {
        return isset(self::PROVIDERS[$platform instanceof Platform ? $platform->value : $platform]);
    }

    public function provider(Platform|string $platform): ChannelProvider
    {
        $key = $platform instanceof Platform ? $platform->value : $platform;

        if (! isset(self::PROVIDERS[$key])) {
            throw new InvalidArgumentException("No channel provider registered for platform [{$key}].");
        }

        return $this->container->make(self::PROVIDERS[$key]);
    }

    /**
     * Catalog of every known platform with its integration status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return array_map(fn (Platform $platform): array => [
            'platform' => $platform->value,
            'name' => $platform->label(),
            'auth_type' => $platform->authType(),
            'status' => $this->has($platform)
                ? ($this->provider($platform)->isConfigured() ? 'available' : 'needs_configuration')
                : 'coming_soon',
        ], Platform::cases());
    }
}
