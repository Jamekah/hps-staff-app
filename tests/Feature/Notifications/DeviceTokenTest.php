<?php

namespace Tests\Feature\Notifications;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_register_a_token(): void
    {
        $this->post('/api/device-tokens', ['token' => 'abc', 'platform' => 'web'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_user_can_register_a_web_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/device-tokens', ['token' => 'fcm-token-1', 'platform' => 'web'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-1',
            'platform' => 'web',
        ]);
    }

    public function test_invalid_platform_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/device-tokens', ['token' => 'fcm-token-1', 'platform' => 'ios'])
            ->assertUnprocessable();
    }

    public function test_existing_token_re_associates_to_the_current_user(): void
    {
        $previousOwner = User::factory()->create();
        $newOwner = User::factory()->create();

        DeviceToken::create([
            'user_id' => $previousOwner->id,
            'token' => 'shared-device-token',
            'platform' => 'web',
        ]);

        $this->actingAs($newOwner)
            ->postJson('/api/device-tokens', ['token' => 'shared-device-token', 'platform' => 'web'])
            ->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'token' => 'shared-device-token',
            'user_id' => $newOwner->id,
        ]);
    }

    public function test_logout_delete_removes_the_token(): void
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'to-be-removed',
            'platform' => 'web',
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/device-tokens', ['token' => 'to-be-removed'])
            ->assertOk();

        $this->assertDatabaseCount('device_tokens', 0);
    }
}
