<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
