<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
