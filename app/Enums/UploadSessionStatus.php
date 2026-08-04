<?php

namespace App\Enums;

enum UploadSessionStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Expired = 'expired';
    case Aborted = 'aborted';
}
