<?php

namespace App\Exceptions;

use Closure;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Used to indicate that the endpoint has been updated and the client should update their code.
 * Client can detect a 400 (Bad Request) response code. The message should always be 'ENDPOINT UPDATED: <message>'
 */
class EndpointUpdatedException extends HttpException
{
    public static function wrap(string $message): Closure
    {
        return static fn () => self::throw($message);
    }

    /**
     * @throws self
     */
    public static function throw(string $message): void
    {
        throw self::create($message);
    }

    public static function create(string $message): self
    {
        return new self(Response::HTTP_BAD_REQUEST, "ENDPOINT UPDATED: $message");
    }
}
