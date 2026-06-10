<?php

namespace Tests\Feature\App\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Tags\Tag;
use Tests\TestCase;
use Tests\Traits\MakesTags;

class TagsTest extends TestCase
{
    use RefreshDatabase;
    use MakesTags;

    public function test_admin_can_retreive_all_tags(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        $tags  = $this->makeTags(5);

        $this->actingAs($admin)
            ->getJson("/admin/report-tags")
            ->assertOk()
            ->assertJsonCount(5)
            ->assertJsonPath('0.id', $tags[0]->id)
            ->assertJsonPath('0.name', $tags[0]->name)
            ->assertJsonPath('0.order_column', $tags[0]->order_column);
    }

    public function test_non_admin_cannot_retrieve_all_tags(): void
    {
        $user = User::factory()->enabled()->create();

        $this->actingAs($user)
            ->getJson("/admin/report-tags")
            ->assertForbidden();
    }

    public function test_admin_can_add_a_tag(): void
    {
        $admin = User::factory()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->postJson("/admin/report-tags", [
                'name' => 'Test Tag',
            ])
            ->assertNoContent();
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_admin_cannot_create_a_duplicate_tag(): void
    {
        $admin = User::factory()->adminRoleUser()->create();

        $this->actingAs($admin)
            ->postJson("/admin/report-tags", [
                'name' => 'Test Tag',
            ])
            ->assertNoContent();
        $this->assertDatabaseCount('tags', 1);

        $this->actingAs($admin)
            ->postJson("/admin/report-tags", [
                'name' => 'Test Tag',
            ])
            ->assertNoContent();
        $this->assertDatabaseCount('tags', 1);

        $this->actingAs($admin)
            ->postJson("/admin/report-tags", [
                'name' => 'Test Tag2',
            ])
            ->assertNoContent();
        $this->assertDatabaseCount('tags', 2);
    }


    public function test_admin_can_edit_a_tag(): void
    {
        $admin   = User::factory()->adminRoleUser()->create();
        $tag     = $this->makeTags(5)->first();
        $newName = 'Test Tag_' . now()->timestamp;

        $this->actingAs($admin)
            ->putJson("/admin/report-tags/$tag->id", [
                'name' => $newName,
            ])
            ->assertNoContent();
        $this->assertDatabaseCount('tags', 5);
        $tag->refresh();
        $this->assertSame($tag->name, $newName);
    }

    public function test_admin_can_delete_a_tag(): void
    {
        $admin   = User::factory()->adminRoleUser()->create();
        /** @var \Spatie\Tags\Tag $tag */
        $tag = $this->makeTags(5)->first();

        $this->actingAs($admin)
            ->deleteJson("/admin/report-tags/$tag->id")
            ->assertNoContent();
        $this->assertDatabaseCount('tags', 4);

        $this->assertModelMissing($tag);
    }

    public function test_admin_can_change_sort_order_of_tags(): void
    {
        $admin = User::factory()->adminRoleUser()->create();
        /** @var \Spatie\Tags\Tag $tag */
        $tags = $this->makeTags(5);

        $this->assertDatabaseCount('tags', 5);
        $dbTags = Tag::all();
        $this->assertSame($tags->pluck('order_column')->toArray(), $dbTags->pluck('order_column')->toArray());

        $this->actingAs($admin)
            ->putJson("/admin/report-tag-sort-order", [
                'ids' => $tags->pluck('id')->reverse()->toArray(),
            ])
            ->assertNoContent();

        $dbTags = Tag::all();
        $this->assertSame($tags->reverse()->pluck('order_column')->toArray(), $dbTags->pluck('order_column')->toArray());
    }
}
