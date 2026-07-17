<?php

namespace App\Services\Rankings;

use App\Models\Keyword;
use App\Models\Product;
use App\Services\Rankings\Contracts\RankProvider;
use App\Services\Rankings\Contracts\RankResult;

class NullRankProvider implements RankProvider
{
    public function lookup(Product $product, Keyword $keyword, string $marketplaceId): RankResult
    {
        return new RankResult(null, null, null, null, 0, 'null');
    }
}
