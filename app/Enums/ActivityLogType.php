<?php

namespace App\Enums;

enum ActivityLogType: string
{
    case ConversationCreated = 'conversation_created';
    case ConversationArchived = 'conversation_archived';
    case ConversationHidden = 'conversation_hidden';
    case MessageSent = 'message_sent';
    case MessageUpdated = 'message_updated';
    case MessageDeleted = 'message_deleted';
    case MessageDeletedForMe = 'message_deleted_for_me';
    case MediaUploaded = 'media_uploaded';
    case ProfileUpdated = 'profile_updated';
    case SettingsUpdated = 'settings_updated';
    case SyncBootstrap = 'sync_bootstrap';
    case SyncAck = 'sync_ack';
}
