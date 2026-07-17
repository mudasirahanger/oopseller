<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_agency_can_create_client_product_and_keyword_project(): void
    {
        [, $organization, $headers] = $this->agencyUser();

        $clientId = $this->withHeaders($headers)->postJson('/api/v1/clients', [
            'name' => 'Real Seller Client',
            'contact_email' => 'seller@example.com',
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->artisan('db:seed');

        $productResponse = $this->withHeaders($headers)->postJson('/api/v1/products', [
            'client_id' => $clientId,
            'asin' => 'B0ABCDEF12',
            'sku' => 'SKU-001',
            'name' => 'Client Product',
            'product_type' => 'HOME',
            'marketplace_id' => 'A21TJRUUN4KGV',
            'import_from_amazon' => false,
        ])->assertCreated();

        $productId = $productResponse->json('data.id');
        $productResponse->assertJsonPath('data.listings.0.seller_sku', 'SKU-001');

        $this->withHeaders($headers)->postJson('/api/v1/keyword-projects', [
            'product_id' => $productId,
            'marketplace_id' => 'A21TJRUUN4KGV',
            'name' => 'India keywords',
            'language' => 'en',
            'keywords' => ['client product', 'best client product'],
        ])->assertCreated()->assertJsonCount(2, 'data.keywords');

        $this->assertDatabaseHas('clients', ['organization_id' => $organization->id, 'name' => 'Real Seller Client']);
        $this->assertDatabaseHas('products', ['organization_id' => $organization->id, 'asin' => 'B0ABCDEF12']);
    }

    public function test_tenant_cannot_attach_another_organizations_product_to_a_task(): void
    {
        [, , $firstHeaders] = $this->agencyUser('first@example.com', 'First Agency');
        [, , $secondHeaders] = $this->agencyUser('second@example.com', 'Second Agency');

        $secondClient = $this->withHeaders($secondHeaders)->postJson('/api/v1/clients', [
            'name' => 'Second Client',
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $secondProduct = $this->withHeaders($secondHeaders)->postJson('/api/v1/products', [
            'client_id' => $secondClient,
            'asin' => 'B0ZZZZZZZZ',
            'name' => 'Private Product',
            'import_from_amazon' => false,
        ])->assertCreated()->json('data.id');

        app('auth')->forgetGuards();

        $firstClient = $this->withHeaders($firstHeaders)->postJson('/api/v1/clients', [
            'name' => 'First Client',
            'status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->withHeaders($firstHeaders)->postJson('/api/v1/tasks', [
            'client_id' => $firstClient,
            'product_id' => $secondProduct,
            'type' => 'listing_audit',
            'title' => 'Invalid cross-tenant task',
            'priority' => 'medium',
        ])->assertNotFound();

        $this->assertDatabaseMissing('agency_tasks', ['title' => 'Invalid cross-tenant task']);
    }

    private function agencyUser(string $email = 'owner@example.com', string $organizationName = 'Agency'): array
    {
        $organization = Organization::create([
            'name' => $organizationName,
            'slug' => str($organizationName)->slug().'-'.str()->random(5),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);
        $user = User::create([
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password123',
            'current_organization_id' => $organization->id,
        ]);
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $organization, [
            'Authorization' => 'Bearer '.$token,
            'X-Organization-Id' => (string) $organization->id,
        ]];
    }
}
