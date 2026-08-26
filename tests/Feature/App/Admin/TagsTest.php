<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Tags\Tag;
use Tests\Traits\MakesTags;

uses(RefreshDatabase::class);

uses(MakesTags::class);

test('admin can retreive all tags', function () {
    $admin = User::factory()->adminRoleUser()->create();
    $tags = $this->makeTags(5);

    $this->actingAs($admin)
        ->getJson('/admin/report-tags')
        ->assertOk()
        ->assertJsonCount(5)
        ->assertJsonPath('0.id', $tags[0]->id)
        ->assertJsonPath('0.name', $tags[0]->name)
        ->assertJsonPath('0.order_column', $tags[0]->order_column);
});

test('non admin cannot retrieve all tags', function () {
    $user = User::factory()->enabled()->create();

    $this->actingAs($user)
        ->getJson('/admin/report-tags')
        ->assertForbidden();
});

test('admin can add a tag', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->postJson('/admin/report-tags', [
            'name' => 'Test Tag',
        ])
        ->assertNoContent();
    $this->assertDatabaseCount('tags', 1);
});

test('admin cannot create a duplicate tag', function () {
    $admin = User::factory()->adminRoleUser()->create();

    $this->actingAs($admin)
        ->postJson('/admin/report-tags', [
            'name' => 'Test Tag',
        ])
        ->assertNoContent();
    $this->assertDatabaseCount('tags', 1);

    $this->actingAs($admin)
        ->postJson('/admin/report-tags', [
            'name' => 'Test Tag',
        ])
        ->assertNoContent();
    $this->assertDatabaseCount('tags', 1);

    $this->actingAs($admin)
        ->postJson('/admin/report-tags', [
            'name' => 'Test Tag2',
        ])
        ->assertNoContent();
    $this->assertDatabaseCount('tags', 2);
});

test('admin can edit a tag', function () {
    $admin = User::factory()->adminRoleUser()->create();
    $tag = $this->makeTags(5)->first();
    $newName = 'Test Tag_'.now()->timestamp;

    $this->actingAs($admin)
        ->putJson("/admin/report-tags/$tag->id", [
            'name' => $newName,
        ])
        ->assertNoContent();
    $this->assertDatabaseCount('tags', 5);
    $tag->refresh();
    expect($newName)->toBe($tag->name);
});

test('admin can delete a tag', function () {
    $admin = User::factory()->adminRoleUser()->create();

    /** @var Tag $tag */
    $tag = $this->makeTags(5)->first();

    $this->actingAs($admin)
        ->deleteJson("/admin/report-tags/$tag->id")
        ->assertNoContent();
    $this->assertDatabaseCount('tags', 4);

    $this->assertModelMissing($tag);
});

test('admin can change sort order of tags', function () {
    $admin = User::factory()->adminRoleUser()->create();

    /** @var Tag $tag */
    $tags = $this->makeTags(5);

    $this->assertDatabaseCount('tags', 5);
    $dbTags = Tag::all();
    expect($dbTags->pluck('order_column')->toArray())->toBe($tags->pluck('order_column')->toArray());

    $this->actingAs($admin)
        ->putJson('/admin/report-tag-sort-order', [
            'ids' => $tags->pluck('id')->reverse()->toArray(),
        ])
        ->assertNoContent();

    $dbTags = Tag::all();
    expect($dbTags->pluck('order_column')->toArray())->toBe($tags->reverse()->pluck('order_column')->toArray());
});
