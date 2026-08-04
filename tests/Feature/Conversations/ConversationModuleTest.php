<?php

namespace Tests\Feature\Conversations;

use App\Enums\ParticipantStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_private_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $token = $this->loginToken($alice);

        $response = $this->withToken($token)
            ->postJson('/api/v1/conversations', [
                'user_id' => $bob->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.type', 'private')
            ->assertJsonCount(2, 'data.conversation.participants');

        $this->assertDatabaseHas('conversations', [
            'created_by_user_id' => $alice->id,
            'type' => 'private',
        ]);
    }

    public function test_creating_same_pair_resumes_existing_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $token = $this->loginToken($alice);

        $first = $this->withToken($token)
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $second = $this->withToken($token)
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id]);

        $second->assertOk()
            ->assertJsonPath('data.conversation.id', $first);
    }

    public function test_user_can_list_and_archive_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $token = $this->loginToken($alice);

        $id = $this->withToken($token)
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $this->withToken($token)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data.conversations');

        $this->withToken($token)
            ->postJson('/api/v1/conversations/'.$id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.conversation.my_status', ParticipantStatus::Archived->value);

        $this->withToken($token)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data.conversations');

        $this->withToken($token)
            ->getJson('/api/v1/conversations?status=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data.conversations');

        $this->withToken($token)
            ->postJson('/api/v1/conversations/'.$id.'/unarchive')
            ->assertOk()
            ->assertJsonPath('data.conversation.my_status', ParticipantStatus::Active->value);
    }

    public function test_user_can_hide_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $token = $this->loginToken($alice);

        $id = $this->withToken($token)
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $this->withToken($token)
            ->postJson('/api/v1/conversations/'.$id.'/hide')
            ->assertOk()
            ->assertJsonPath('data.conversation.my_status', ParticipantStatus::Hidden->value);

        $this->withToken($token)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data.conversations');

        $this->flushAuthState();

        $this->actingAs($bob, 'api')
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data.conversations');

        // Reprise via create
        $this->withToken($token)
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->assertOk()
            ->assertJsonPath('data.conversation.id', $id)
            ->assertJsonPath('data.conversation.my_status', ParticipantStatus::Active->value);
    }

    public function test_cannot_create_conversation_with_self(): void
    {
        $alice = User::factory()->create();
        $token = $this->loginToken($alice);

        $this->withToken($token)
            ->postJson('/api/v1/conversations', ['user_id' => $alice->id])
            ->assertStatus(422);
    }

    public function test_non_participant_cannot_view_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $charlie = User::factory()->create();

        $id = $this->actingAs($alice, 'api')
            ->postJson('/api/v1/conversations', ['user_id' => $bob->id])
            ->json('data.conversation.id');

        $this->flushAuthState();

        $this->actingAs($charlie, 'api')
            ->getJson('/api/v1/conversations/'.$id)
            ->assertNotFound();
    }

    private function loginToken(User $user): string
    {
        $this->withoutToken();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->json('data.tokens.access_token');

        $this->assertNotEmpty($token, 'Login failed for '.$user->email);

        return $token;
    }
}
