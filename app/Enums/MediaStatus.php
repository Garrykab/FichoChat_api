<?php

namespace App\Enums;

enum MediaStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Ready = 'ready';
    case Failed = 'failed';
}
