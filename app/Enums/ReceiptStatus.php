<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
}
