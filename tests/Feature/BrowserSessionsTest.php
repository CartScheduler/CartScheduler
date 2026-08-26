<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('other browser sessions can be logged out', function () {
    $this->actingAs($user = User::factory()->create());

    $response = $this->delete('/user/other-browser-sessions', [
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
});
