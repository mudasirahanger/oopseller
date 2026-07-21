<?php

namespace Tests\Feature;

use App\Models\AmazonAccount;
use App\Models\Client;
use App\Models\Organization;
use App\Services\Amazon\Contracts\SellerDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonSandboxOrderItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sandbox_order_sync_survives_get_order_items_rejecting_the_mock_order_id(): void
    {
        // Amazon's Sandbox lets GetOrders return a canned mock order, but
        // GetOrderItems only recognizes its own separate small set of test
        // order IDs — calling it with the order ID GetOrders just returned
        // routinely fails with "Could not match input arguments" even though
        // the parent order fetch succeeded. Before this fix, that exception
        // was uncaught and marked the whole sync run as failed.
        config()->set('services.amazon.lwa_client_id', 'client-id');
        config()->set('services.amazon.lwa_client_secret', 'client-secret');
        config()->set('services.amazon.application_id', 'app-id');
        config()->set('services.amazon.redirect_uri', 'https://api.example.com/callback');

        $organization = Organization::create([
            'name' => 'Sandbox Order Agency', 'slug' => 'sandbox-order-agency-'.str()->random(5),
            'timezone' => 'Asia/Kolkata', 'currency' => 'INR',
        ]);
        $client = Client::create([
            'organization_id' => $organization->id, 'name' => 'Sandbox Order Client',
            'slug' => 'sandbox-order-client-'.str()->random(4), 'status' => 'active',
        ]);
        $account = AmazonAccount::create([
            'organization_id' => $organization->id,
            'client_id' => $client->id,
            'account_identifier' => 'A1SANDBOXORDERS',
            'name' => 'Sandbox Orders Account',
            'region' => 'na',
            'refresh_token' => 'Atzr|IwEBISandboxOrdersRefreshTokenLongEnough',
            'status' => 'active',
            'metadata' => ['sandbox' => true],
        ]);

        Http::fake([
            '*/auth/o2/token' => Http::response(['access_token' => 'test-access-token', 'expires_in' => 3600]),
            '*/orders/v0/orders/TEST_CASE_ORDER*/orderItems' => Http::response([
                'errors' => [['message' => 'Could not match input arguments']],
            ], 400),
            '*/orders/v0/orders*' => Http::response([
                'payload' => [
                    'Orders' => [
                        ['AmazonOrderId' => 'TEST_CASE_ORDER', 'OrderStatus' => 'Shipped', 'PurchaseDate' => now()->toIso8601String()],
                    ],
                ],
            ]),
        ]);

        $orders = iterator_to_array(app(SellerDataProvider::class)->importOrders(
            $account,
            ['ATVPDKIKX0DER'],
            now()->subDay(),
        ));

        $this->assertCount(1, $orders);
        $this->assertSame('TEST_CASE_ORDER', $orders[0]['AmazonOrderId']);
        $this->assertSame([], $orders[0]['OrderItems']);
    }
}
