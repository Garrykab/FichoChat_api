<?php

namespace App\Enums;

enum EnvelopeContentType: string
{
    case Message = 'message';
    case Media = 'media';
    case Sync = 'sync';
    case Avatar = 'avatar';
}
