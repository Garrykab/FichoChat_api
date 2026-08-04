<?php

namespace App\Actions\Keys;

use App\Enums\DeviceStatus;
use App\Enums\EnvelopeContentType;
use App\Models\KeyEnvelope;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreKeyEnvelopesAction
{
    /**
     * @param  array{
     *     content_type: string,
     *     content_id: string,
     *     sender_device_id: string,
     *     envelopes: list<array{
     *         recipient_device_id: string,
     *         encrypted_cek: string,
     *         ephemeral_public_key: string,
     *         iv: string,
     *         algorithm?: string,
     *         key_version?: int
     *     }>
     * }  $data
     * @return Collection<int, KeyEnvelope>
     */
    public function execute(User $user, array $data): Collection
    {
        $sender = UserDevice::query()
            ->where('id', $data['sender_device_id'])
            ->where('user_id', $user->id)
            ->first();

        if ($sender === null || ! $sender->isApproved()) {
            throw ValidationException::withMessages([
                'sender_device_id' => ['Sender device must be an approved device of the authenticated user.'],
            ]);
        }

        $contentType = EnvelopeContentType::from($data['content_type']);
        $recipientIds = collect($data['envelopes'])->pluck('recipient_device_id')->unique()->values();

        $recipients = UserDevice::query()
            ->whereIn('id', $recipientIds)
            ->where('status', DeviceStatus::Approved)
            ->get()
            ->keyBy('id');

        if ($recipients->count() !== $recipientIds->count()) {
            throw ValidationException::withMessages([
                'envelopes' => ['All recipient devices must exist and be approved.'],
            ]);
        }

        return DB::transaction(function () use ($data, $contentType, $sender, $recipients) {
            $created = collect();

            foreach ($data['envelopes'] as $envelopeData) {
                /** @var UserDevice $recipient */
                $recipient = $recipients->get($envelopeData['recipient_device_id']);

                $envelope = KeyEnvelope::query()->updateOrCreate(
                    [
                        'content_type' => $contentType,
                        'content_id' => $data['content_id'],
                        'recipient_device_id' => $recipient->id,
                    ],
                    [
                        'sender_device_id' => $sender->id,
                        'encrypted_cek' => $envelopeData['encrypted_cek'],
                        'ephemeral_public_key' => $envelopeData['ephemeral_public_key'],
                        'iv' => $envelopeData['iv'],
                        'algorithm' => $envelopeData['algorithm'] ?? 'ECDH-P256+HKDF-SHA256+AES-256-GCM',
                        'key_version' => $envelopeData['key_version'] ?? 1,
                    ],
                );

                $created->push($envelope);
            }

            return $created;
        });
    }
}
