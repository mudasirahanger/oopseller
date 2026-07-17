<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\Listing;

class ListingOptimizer
{
    public function audit(Listing $listing): array
    {
        $keywords = Keyword::query()
            ->whereHas('project', fn ($q) => $q->where('product_id', $listing->product_id)->where('marketplace_id', $listing->marketplace_id))
            ->where('status', 'active')->get();

        $text = mb_strtolower(implode(' ', array_filter([
            $listing->title,
            implode(' ', $listing->bullet_points ?? []),
            $listing->description,
            implode(' ', $listing->backend_terms ?? []),
        ])));

        $covered = $keywords->filter(fn (Keyword $keyword) => str_contains($text, mb_strtolower($keyword->phrase)))->count();
        $coverage = $keywords->count() ? (int) round(($covered / $keywords->count()) * 100) : 0;

        $breakdown = [
            'title' => $this->scoreTitle($listing->title),
            'bullets' => min(15, count($listing->bullet_points ?? []) * 3),
            'keyword_coverage' => (int) round($coverage * .20),
            'attributes' => min(15, count(array_filter($listing->attributes ?? []))),
            'images' => min(15, $listing->image_count * 2),
            'description_a_plus' => ($listing->description ? 4 : 0) + ($listing->a_plus_status === 'published' ? 6 : 0),
            'conversion_readiness' => $listing->status === 'active' ? 10 : 2,
        ];

        $recommendations = [];
        if ($breakdown['title'] < 10) {
            $recommendations[] = 'Rewrite the title with the primary product phrase, important differentiator, and readable structure.';
        }
        if ($breakdown['bullets'] < 12) {
            $recommendations[] = 'Add five benefit-led bullet points with evidence and product-specific details.';
        }
        if ($coverage < 70) {
            $recommendations[] = "Increase tracked keyword coverage from {$coverage}% without keyword stuffing.";
        }
        if ($listing->image_count < 7) {
            $recommendations[] = 'Prepare at least seven high-quality images covering benefits, dimensions, use, and trust.';
        }
        if ($listing->a_plus_status !== 'published') {
            $recommendations[] = 'Create or publish A+ Content if the brand is eligible.';
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Listing foundation is strong; run a controlled optimization experiment and monitor conversion.';
        }

        return ['score' => array_sum($breakdown), 'breakdown' => $breakdown, 'recommendations' => $recommendations, 'keyword_coverage_percent' => $coverage];
    }

    private function scoreTitle(?string $title): int
    {
        $length = mb_strlen(trim((string) $title));

        return match (true) {
            $length === 0 => 0,
            $length < 60 => 7,
            $length <= 180 => 15,
            $length <= 200 => 11,
            default => 6,
        };
    }
}
