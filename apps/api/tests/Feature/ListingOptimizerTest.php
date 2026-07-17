<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Keyword;
use App\Models\KeywordProject;
use App\Models\Listing;
use App\Models\Organization;
use App\Models\Product;
use App\Services\ListingOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingOptimizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scores_a_listing_and_returns_recommendations(): void
    {
        $org = Organization::create(['name' => 'Agency', 'slug' => 'agency', 'currency' => 'INR']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client', 'slug' => 'client', 'status' => 'active']);
        $product = Product::create(['organization_id' => $org->id, 'client_id' => $client->id, 'asin' => 'B000000001', 'name' => 'Test Product', 'status' => 'active']);
        $listing = Listing::create(['organization_id' => $org->id, 'client_id' => $client->id, 'product_id' => $product->id, 'marketplace_id' => 'A21TJRUUN4KGV', 'title' => 'Premium Test Product for Home Use', 'bullet_points' => ['First benefit', 'Second benefit'], 'description' => 'Useful test product', 'backend_terms' => ['test product'], 'attributes' => ['brand' => 'Test'], 'image_count' => 3, 'a_plus_status' => 'not_started', 'status' => 'active']);
        $project = KeywordProject::create(['organization_id' => $org->id, 'client_id' => $client->id, 'product_id' => $product->id, 'marketplace_id' => 'A21TJRUUN4KGV', 'name' => 'Keywords', 'status' => 'active']);
        Keyword::create(['organization_id' => $org->id, 'client_id' => $client->id, 'keyword_project_id' => $project->id, 'phrase' => 'test product', 'status' => 'active']);
        $result = app(ListingOptimizer::class)->audit($listing);
        $this->assertGreaterThan(0, $result['score']);
        $this->assertNotEmpty($result['recommendations']);
    }
}
