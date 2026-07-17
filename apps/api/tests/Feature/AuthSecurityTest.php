<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $this->makeMember('owner@example.com', 'owner');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_password_reset_flow_updates_password_and_revokes_tokens(): void
    {
        Notification::fake();
        [$user] = $this->makeMember('owner@example.com', 'owner');
        $user->createToken('web');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'owner@example.com'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'owner@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'new-password-123',
        ])->assertOk();
    }

    public function test_forgot_password_does_not_reveal_unknown_emails(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email address has an account, a password reset link has been sent.');
    }

    public function test_viewer_role_is_read_only(): void
    {
        [, , $headers] = $this->makeMember('viewer@example.com', 'viewer');

        $this->withHeaders($headers)->getJson('/api/v1/clients')->assertOk();
        $this->withHeaders($headers)->postJson('/api/v1/clients', [
            'name' => 'Should Fail',
        ])->assertForbidden();
    }

    public function test_member_cannot_delete_client_but_owner_can(): void
    {
        [, $organization, $ownerHeaders] = $this->makeMember('owner2@example.com', 'owner');

        $clientId = $this->withHeaders($ownerHeaders)->postJson('/api/v1/clients', [
            'name' => 'Deletable Client',
        ])->assertCreated()->json('data.id');

        $member = User::create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => 'password123',
            'current_organization_id' => $organization->id,
        ]);
        $organization->users()->attach($member->id, ['role' => 'member']);
        $memberHeaders = [
            'Authorization' => 'Bearer '.$member->createToken('test')->plainTextToken,
            'X-Organization-Id' => (string) $organization->id,
        ];

        app('auth')->forgetGuards();
        $this->withHeaders($memberHeaders)->deleteJson("/api/v1/clients/{$clientId}")->assertForbidden();
        app('auth')->forgetGuards();
        $this->withHeaders($ownerHeaders)->deleteJson("/api/v1/clients/{$clientId}")->assertNoContent();
    }

    private function makeMember(string $email, string $role): array
    {
        $organization = Organization::create([
            'name' => 'Agency '.$role,
            'slug' => str('agency-'.$role)->slug().'-'.str()->random(5),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);
        $user = User::create([
            'name' => 'User',
            'email' => $email,
            'password' => 'password123',
            'current_organization_id' => $organization->id,
        ]);
        $organization->users()->attach($user->id, ['role' => $role]);

        return [$user, $organization, [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Organization-Id' => (string) $organization->id,
        ]];
    }
}
