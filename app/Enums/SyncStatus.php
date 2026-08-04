<?php

namespace App\Enums;

enum SyncStatus: string
{
    case PendingBootstrap = 'pending_bootstrap';
    case Ready = 'ready';
}
