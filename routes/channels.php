<?php

use App\Models\Conversation;
use App\Models\User;
use App\Support\UserPresence;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function (User $user, string $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId): bool {
    return Conversation::query()
        ->whereKey($conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});

/**
 * Presence : membres de la conversation.
 * shares_presence=false → le client ne doit pas afficher « En ligne » / last seen pour ce membre.
 *
 * @return array{id: string, username: string, shares_presence: bool}|false
 */
Broadcast::channel('conversation.{conversationId}.presence', function (User $user, string $conversationId) {
    $isParticipant = Conversation::query()
        ->whereKey($conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists();

    if (! $isParticipant) {
        return false;
    }

    $user->loadMissing('settings');

    return [
        'id' => $user->id,
        'username' => $user->username,
        'shares_presence' => UserPresence::sharesPresence($user),
    ];
});
