<?php

use App\Actions\DescribeJobFailure;

test('common failures are explained in plain english', function (string $exception, string $expected) {
    expect((new DescribeJobFailure)->execute($exception))->toStartWith($expected);
})->with([
    'bad password' => ['Symfony\Component\Mailer\Exception\TransportException: Expected response code "235" but got code "535", with message "535 5.7.8 Authentication failed". in /var/www/vendor/a.php:10', 'The mail server rejected the username or password.'],
    'no connection' => ['Symfony\Component\Mailer\Exception\TransportException: Connection could not be established with host "smtp.example.test:587": stream_socket_client(): php_network_getaddresses: getaddrinfo failed', "Couldn't connect to the mail server."],
    'connection timeout' => ['TransportException: Connection to "smtp.example.test:465" timed out.', "Couldn't connect to the mail server."],
    'bad recipient' => ['TransportException: Expected response code "250/251/252" but got code "550", with message "550 5.1.1 User unknown".', 'The mail server refused the recipient address.'],
    'throttled' => ['TransportException: Expected response code "250" but got code "421", with message "421 Too many messages".', 'The mail server is limiting how many emails can be sent right now.'],
    'tls' => ['TransportException: Unable to connect with STARTTLS: stream_socket_enable_crypto(): SSL operation failed', 'The secure connection to the mail server failed.'],
    'deleted record' => ['Illuminate\Database\Eloquent\ModelNotFoundException: No query results for model [App\Models\User].', 'The record this job was about (for example a user) was deleted before the job ran.'],
    'too many attempts' => ['Illuminate\Queue\MaxAttemptsExceededException: App\Jobs\X has been attempted too many times.', 'The job was attempted too many times without succeeding.'],
    'timeout' => ['Illuminate\Queue\TimeoutExceededException: App\Jobs\X has timed out.', 'The job took too long and was stopped.'],
    'database' => ['Illuminate\Database\QueryException: SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, SQL: select 421)', 'A database error stopped the job.'],
]);

test('unknown failures show the original message without the class or file', function () {
    $describer = new DescribeJobFailure;
    $exception = "RuntimeException: The widget exploded in /var/www/html/app/Widget.php:42\nStack trace:\n#0 {main}";

    expect($describer->execute($exception))->toBe('Something went wrong: The widget exploded')
        ->and($describer->message($exception))->toBe('The widget exploded');
});

test('missing error details are explained', function () {
    expect((new DescribeJobFailure)->execute(null))->toBe('No error details were recorded.')
        ->and((new DescribeJobFailure)->execute('   '))->toBe('No error details were recorded.');
});
