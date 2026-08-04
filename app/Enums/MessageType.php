<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';
    case Audio = 'audio';
    case Voice = 'voice';
}
