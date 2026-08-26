<?php

use App\Exceptions\EndpointUpdatedException;

test('throw', function () {
    $this->assertThrows(
        fn () => EndpointUpdatedException::throw('A test message'),
        EndpointUpdatedException::class,
        'A test message');
});

test('wrap', function () {
    $this->expectException(EndpointUpdatedException::class);
    $this->expectExceptionMessage('A test message');

    $exception = EndpointUpdatedException::wrap('A test message');
    expect($exception)->toBeCallable();
    $exception();
});

test('create', function () {
    $this->assertThrows(
        fn () => throw EndpointUpdatedException::create('A test message'),
        EndpointUpdatedException::class,
        'A test message');
});
