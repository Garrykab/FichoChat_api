<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Hidden = 'hidden';
}
