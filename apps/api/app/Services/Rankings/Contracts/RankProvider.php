<?php

namespace App\Services\Rankings\Contracts;

use App\Models\Keyword;
use App\Models\Product;

final readonly class RankResult
{
    public function __construct(public ?int $organicPosition, public ?int $sponsoredPosition, public ?int $pageNumber, public ?int $resultCount, public float $confidence, public string $provider) {}
}
interface RankProvider
{
    public function lookup(Product $product, Keyword $keyword, string $marketplaceId): RankResult;
}
