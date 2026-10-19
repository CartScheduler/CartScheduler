<?php

namespace App\Exceptions;

use App\Actions\ErrorApiResource;
use RuntimeException;

class ShiftAvailabilityException extends RuntimeException
{
    private int $exceptionType;

    public static function notAvailableYet(): self
    {
        return (new self('Shift is not available yet'))
            ->setExceptionType(ErrorApiResource::CODE_SHIFT_NOT_AVAILABLE_YET);
    }

    public static function notAvailableAnymore(): self
    {
        return (new self('Shift is not available anymore'))
            ->setExceptionType(ErrorApiResource::CODE_SHIFT_NO_LONGER_AVAILABLE);
    }

    public static function shiftIsFull(): self
    {
        return (new self('Shift is at maximum capacity'))
            ->setExceptionType(ErrorApiResource::CODE_SHIFT_AT_MAX_CAPACITY);
    }

    public function getExceptionType(): string
    {
        return $this->exceptionType;
    }

    public function setExceptionType(int $exceptionType): ShiftAvailabilityException
    {
        $this->exceptionType = $exceptionType;

        return $this;
    }
}
