<?php

namespace App\Enums;

enum EmailTemplateTriggerType: string
{
    case System = 'system';
    case Manual = 'manual';
}
