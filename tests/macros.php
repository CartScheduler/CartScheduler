<?php

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

TestResponse::macro('assertJsonHasKeys', function (string $jsonPath, string ...$keys) {
    $items = $this->json($jsonPath);

    foreach ($keys as $key) {
        Assert::assertArrayHasKey($key, $items);
    }

    return $this;
});

TestResponse::macro('assertContainsStringIgnoringCase', function (string $jsonPath, string $string) {
    Assert::assertStringContainsStringIgnoringCase($string, $this->json($jsonPath));

    return $this;
});
