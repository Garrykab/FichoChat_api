<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Revoked = 'revoked';
}
