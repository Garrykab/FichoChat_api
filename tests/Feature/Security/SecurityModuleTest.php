<?php

namespace Tests\Feature\Security;

use App\Enums\ActivityLogType;
use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_failed_login_are_logged(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => SecurityEventType::Login->value,
            'result' => 'success',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => SecurityEventType::LoginFailed->value,
            'result' => 'failure',
        ]);
    }

    public function test_register_creates_security_event(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'audit@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'terms_accepted' => true,
        ])->assertCreated();

        $user = User::query()->where('email', 'audit@example.com')->firstOrFail();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => SecurityEventType::Register->value,
        ]);
    }

    public function test_device_register_approve_revoke_are_logged(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $first = $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => 'MacBook',
                'platform' => 'web',
                'public_key' => $this->publicKey('device-1'),
            ])
            ->assertCreated()
            ->json('data.device');

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => SecurityEventType::DeviceRegistered->value,
            'device_id' => $first['id'],
        ]);

        $second = $this->withToken($token)
            ->postJson('/api/v1/devices', [
                'name' => 'Phone',
                'platform' => 'ios',
                'public_key' => $this->publicKey('device-2'),
            ])
            ->assertCreated()
            ->json('data.device');

        $this->withToken($token)
            ->postJson('/api/v1/devices/'.$second['id'].'/approve', [
                'actor_device_id' => $first['id'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => SecurityEventType::DeviceApproved->value,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/devices/'.$second['id'].'/revoke', [
                'actor_device_id' => $first['id'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => SecurityEventType::DeviceRevoked->value,
        ]);
    }

    public function test_conversation_create_and_hide_are_activity_logged(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $token = $this->loginToken($alice);

        $conversationId = $this->withToken($token)
            ->postJson('/api/v1/conversations', [
                'user_id' => $bob->id,
            ])
            ->assertCreated()
            ->json('data.conversation.id');

        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $alice->id,
            'event' => ActivityLogType::ConversationCreated->value,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/conversations/'.$conversationId.'/hide')
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $alice->id,
            'event' => ActivityLogType::ConversationHidden->value,
        ]);
    }

    public function test_user_can_list_own_security_events_and_view_is_logged(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceToken = $this->loginToken($alice);
        $this->loginToken($bob);

        $this->assertGreaterThan(0, SecurityEvent::query()->where('user_id', $alice->id)->count());
        $this->assertGreaterThan(0, SecurityEvent::query()->where('user_id', $bob->id)->count());

        $response = $this->withToken($aliceToken)
            ->getJson('/api/v1/security/events')
            ->assertOk()
            ->assertJsonPath('success', true);

        $events = $response->json('data.events');
        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertContains($event['type'], [
                SecurityEventType::Login->value,
                SecurityEventType::LogsViewed->value,
            ]);
        }

        $this->assertDatabaseHas('security_events', [
            'user_id' => $alice->id,
            'type' => SecurityEventType::LogsViewed->value,
        ]);

        $ids = collect($events)->pluck('id');
        $bobEventIds = SecurityEvent::query()
            ->where('user_id', $bob->id)
            ->pluck('id');

        foreach ($bobEventIds as $bobId) {
            $this->assertFalse($ids->contains($bobId));
        }
    }

    public function test_user_can_list_own_activity_logs(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $token = $this->loginToken($alice);

        $this->withToken($token)
            ->postJson('/api/v1/conversations', [
                'user_id' => $bob->id,
            ])
            ->assertCreated();

        $response = $this->withToken($token)
            ->getJson('/api/v1/activity/logs')
            ->assertOk();

        $types = collect($response->json('data.logs'))->pluck('type');
        $this->assertTrue($types->contains(ActivityLogType::ConversationCreated->value));

        $this->assertDatabaseHas('security_events', [
            'user_id' => $alice->id,
            'type' => SecurityEventType::LogsViewed->value,
        ]);
    }

    public function test_audit_logger_sanitizes_sensitive_context(): void
    {
        $user = User::factory()->create();

        app(\App\Services\Audit\AuditLogger::class)->security(
            SecurityEventType::Login,
            $user,
            [
                'password' => 'secret',
                'ciphertext' => 'cipher',
                'safe' => 'ok',
            ],
        );

        $event = SecurityEvent::query()
            ->where('user_id', $user->id)
            ->where('type', SecurityEventType::Login->value)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame(['safe' => 'ok'], $event->context);
    }

    public function test_password_change_is_logged(): void
    {
        $user = User::factory()->create();
        $token = $this->loginToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/users/me/password', [
                'current_password' => 'password',
                'password' => 'NewPassword1!',
                'password_confirmation' => 'NewPassword1!',
            ])
            ->assertOk();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'type' => SecurityEventType::PasswordChanged->value,
        ]);
    }

    private function loginToken(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens.access_token');
    }

    private function publicKey(string $seed): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".base64_encode(str_pad($seed, 64, 'x'))."\n-----END PUBLIC KEY-----";
    }
}
