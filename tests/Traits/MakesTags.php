<?php

namespace Tests\Traits;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Spatie\Tags\Tag;

trait MakesTags
{
    use WithFaker;

    public function makeTags($count = 1): Collection
    {
        $tags = collect();

        $sortOrder = 0;
        foreach (range(0, $count - 1) as $ignored) {
            // Not using a factory because Tags are a 3rd party model
            $name = $this->faker->name;
            $tags[] = Tag::create([
                'name' => $name,
                'slug' => $this->faker->slug(),
                'type' => 'reports',
                'order_column' => $sortOrder++,
            ]);
        }

        return $tags;
    }
}
