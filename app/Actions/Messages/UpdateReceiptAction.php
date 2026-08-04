<?php

namespace App\Actions\Messages;

use App\Enums\ReceiptStatus;
use App\Events\MessageReceiptUpdated;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageReceipt;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSetting;
use Illuminate\Validation\ValidationException;

class UpdateReceiptAction
{
    /**
     * @param  array{status: string, device_id?: string|null}  $data
     */
    public function execute(User $user, Message $message, array $data): MessageReceipt
    {
        if ($message->sender_user_id === $user->id) {
            throw ValidationException::withMessages([
                'message' => ['Sender does not acknowledge their own message.'],
            ]);
        }

        $isParticipant = ConversationParticipant::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isParticipant) {
            throw ValidationException::withMessages([
                'message' => ['You are not a participant of this conversation.'],
            ]);
        }

        $status = ReceiptStatus::from($data['status']);

        if ($status === ReceiptStatus::Read) {
            $settings = UserSetting::query()->where('user_id', $user->id)->first();
            if ($settings !== null && ! $settings->send_read_receipts) {
                throw ValidationException::withMessages([
                    'status' => ['Read receipts are disabled in your privacy settings.'],
                ]);
            }
        }

        $deviceId = $data['device_id'] ?? null;

        if ($deviceId !== null) {
            $device = UserDevice::query()
                ->where('id', $deviceId)
                ->where('user_id', $user->id)
                ->first();

            if ($device === null || ! $device->isApproved()) {
                throw ValidationException::withMessages([
                    'device_id' => ['Device must be an approved device of the authenticated user.'],
                ]);
            }
        }

        /** @var MessageReceipt $receipt */
        $receipt = MessageReceipt::query()->firstOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $user->id,
            ],
            [
                'status' => ReceiptStatus::Sent,
            ],
        );

        $updates = ['device_id' => $deviceId ?? $receipt->device_id];

        if ($status === ReceiptStatus::Delivered) {
            if ($receipt->status === ReceiptStatus::Read) {
                return $receipt;
            }
            $updates['status'] = ReceiptStatus::Delivered;
            $updates['delivered_at'] = $receipt->delivered_at ?? now();
        }

        if ($status === ReceiptStatus::Read) {
            $updates['status'] = ReceiptStatus::Read;
            $updates['delivered_at'] = $receipt->delivered_at ?? now();
            $updates['read_at'] = now();

            ConversationParticipant::query()
                ->where('conversation_id', $message->conversation_id)
                ->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);
        }

        if ($status === ReceiptStatus::Sent) {
            throw ValidationException::withMessages([
                'status' => ['Cannot set status back to sent.'],
            ]);
        }

        $receipt->forceFill($updates)->save();
        $fresh = $receipt->fresh();
        MessageReceiptUpdated::dispatch($fresh);

        return $fresh;
    }
}
