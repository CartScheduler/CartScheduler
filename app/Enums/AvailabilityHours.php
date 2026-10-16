<?php

namespace App\Enums;

enum AvailabilityHours: int
{
    case Zero = 0;
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Four = 4;
    case Five = 5;
    case Six = 6;
    case Seven = 7;
    case Eight = 8;
    case Nine = 9;
    case Ten = 10;
    case Eleven = 11;
    case Twelve = 12;
    case Thirteen = 13;
    case Fourteen = 14;
    case Fifteen = 15;
    case Sixteen = 16;
    case Seventeen = 17;
    case Eighteen = 18;
    case Nineteen = 19;
    case Twenty = 20;
    case TwentyOne = 21;
    case TwentyTwo = 22;
    case TwentyThree = 23;

    public static function values(): array
    {
        return array_map(fn (self $hourPart) => $hourPart->value, self::cases());
    }
}
