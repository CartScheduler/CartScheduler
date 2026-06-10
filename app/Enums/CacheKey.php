<?php

namespace App\Enums;

enum CacheKey: string
{
    case TotalUsers = 'total-users';
    case TotalLocations = 'total-locations';
    case ShiftFilledData = 'shift-filled-data';
    case OutstandingReports = 'outstanding-reports';
}
