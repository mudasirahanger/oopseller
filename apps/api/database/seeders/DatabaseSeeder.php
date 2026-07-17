<?php

namespace Database\Seeders;

use App\Models\Marketplace;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $marketplaces = [
            ['amazon_marketplace_id' => 'A21TJRUUN4KGV', 'country_code' => 'IN', 'name' => 'Amazon India', 'currency' => 'INR', 'domain' => 'amazon.in', 'region' => 'eu'],
            ['amazon_marketplace_id' => 'A1F83G8C2ARO7P', 'country_code' => 'GB', 'name' => 'Amazon United Kingdom', 'currency' => 'GBP', 'domain' => 'amazon.co.uk', 'region' => 'eu'],
            ['amazon_marketplace_id' => 'ATVPDKIKX0DER', 'country_code' => 'US', 'name' => 'Amazon United States', 'currency' => 'USD', 'domain' => 'amazon.com', 'region' => 'na'],
            ['amazon_marketplace_id' => 'A2VIGQ35RCS4UG', 'country_code' => 'AE', 'name' => 'Amazon United Arab Emirates', 'currency' => 'AED', 'domain' => 'amazon.ae', 'region' => 'eu'],
            ['amazon_marketplace_id' => 'A17E79C6D8DWNP', 'country_code' => 'SA', 'name' => 'Amazon Saudi Arabia', 'currency' => 'SAR', 'domain' => 'amazon.sa', 'region' => 'eu'],
        ];

        foreach ($marketplaces as $marketplace) {
            Marketplace::updateOrCreate(
                ['amazon_marketplace_id' => $marketplace['amazon_marketplace_id']],
                $marketplace,
            );
        }
    }
}
