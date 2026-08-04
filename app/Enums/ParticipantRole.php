<?php

namespace App\Enums;

enum ParticipantRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
