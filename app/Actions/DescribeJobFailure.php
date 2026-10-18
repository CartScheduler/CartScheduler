<?php

namespace App\Actions;

use Illuminate\Support\Str;

/**
 * Turns a queue or mail exception into a sentence an administrator can act on.
 */
class DescribeJobFailure
{
    /**
     * Ordered patterns: the first match wins.
     *
     * @var array<string, string>
     */
    private const array PATTERNS = [
        '/ModelNotFoundException|No query results for model/i' => 'The record this job was about (for example a user) was deleted before the job ran.',
        '/SQLSTATE/i' => 'A database error stopped the job.',
        '/MaxAttemptsExceededException|attempted too many times/i' => 'The job was attempted too many times without succeeding.',
        '/\b535\b|authentication (failed|unsuccessful)|username and password not accepted|Expected response code "235"/i' => 'The mail server rejected the username or password. Check MAIL_USERNAME and MAIL_PASSWORD.',
        '/connection could not be established|connection refused|getaddrinfo|could not resolve host|network is unreachable|connection timed out|connection to .+ timed out/i' => "Couldn't connect to the mail server. Check MAIL_HOST and MAIL_PORT, or the mail server may be down.",
        '/\b(550|551|553)\b|mailbox (unavailable|not found)|user unknown|recipient address rejected|no such user/i' => 'The mail server refused the recipient address. The email address may be wrong or no longer exist.',
        '/\b(421|450|451|452)\b|too many|rate limit|try again later|quota/i' => 'The mail server is limiting how many emails can be sent right now. Retrying later should work.',
        '/ssl|tls|certificate|crypto/i' => 'The secure connection to the mail server failed. Check MAIL_SCHEME/MAIL_ENCRYPTION and MAIL_PORT match what the server expects.',
        '/TimeoutExceededException|has timed out|timed out/i' => 'The job took too long and was stopped.',
    ];

    public function execute(?string $exception): string
    {
        if ($exception === null || trim($exception) === '') {
            return 'No error details were recorded.';
        }

        $subject = $this->withoutLocation($this->firstLine($exception));

        foreach (self::PATTERNS as $pattern => $description) {
            if (preg_match($pattern, $subject)) {
                return $description;
            }
        }

        return 'Something went wrong: '.Str::limit($this->message($exception), 200);
    }

    /**
     * The exception's first line without the class name and file location.
     */
    public function message(string $exception): string
    {
        $line = $this->withoutLocation($this->firstLine($exception));

        return trim(preg_replace('/^[\w\\\\]+(Exception|Error):\s*/', '', $line) ?? $line);
    }

    private function withoutLocation(string $line): string
    {
        return preg_replace('/ in \/\S+:\d+$/', '', $line) ?? $line;
    }

    private function firstLine(string $exception): string
    {
        return trim(Str::before($exception, "\n"));
    }
}
