<?php

namespace AnourValar\EloquentValidation\Tests\Features\Relations;

use AnourValar\EloquentValidation\Tests\AbstractSuite;
use AnourValar\EloquentValidation\Tests\Models\Directory;
use AnourValar\EloquentValidation\Tests\Models\User;

class BelongsToJsonTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_get_results()
    {
        $tag1 = $this->createDirectory('a');
        $this->createDirectory('b'); // not referenced
        $tag3 = $this->createDirectory('c');

        // only the referenced directories are returned
        $user = $this->createUser([$tag1->id, $tag3->id]);
        $this->assertSame(
            [$tag1->id, $tag3->id],
            $user->directories()->orderBy('id', 'asc')->pluck('id')->toArray()
        );

        // empty list => empty collection (no query)
        $empty = $this->createUser(null);
        $this->assertSame([], $empty->directories()->pluck('id')->toArray());
    }

    /**
     * @return void
     */
    public function test_eager_loading()
    {
        $tag1 = $this->createDirectory('a');
        $tag2 = $this->createDirectory('b');

        $user1 = $this->createUser([$tag1->id]);
        $user2 = $this->createUser([$tag1->id, $tag2->id]);
        $user3 = $this->createUser(null);

        $users = User::query()->with('directories')->findMany([$user1->id, $user2->id, $user3->id])->keyBy('id');

        $this->assertSame([$tag1->id], $users[$user1->id]->directories->pluck('id')->toArray());
        $this->assertSame([$tag1->id, $tag2->id], $users[$user2->id]->directories->pluck('id')->sort()->values()->toArray());
        $this->assertSame([], $users[$user3->id]->directories->pluck('id')->toArray());
    }

    /**
     * @return void
     */
    public function test_where_has()
    {
        $tag1 = $this->createDirectory('a');
        $tag2 = $this->createDirectory('b');

        $user1 = $this->createUser([$tag1->id]);
        $user2 = $this->createUser([$tag2->id]);
        $this->createUser(null);

        // whereHas with an inner constraint
        $this->assertSame(
            [$user1->id],
            User::query()->whereHas('directories', fn ($query) => $query->where('id', '=', $tag1->id))
                ->orderBy('id', 'asc')->pluck('id')->toArray()
        );

        // has(): any user carrying at least one directory
        $this->assertSame(
            [$user1->id, $user2->id],
            User::query()->has('directories')->orderBy('id', 'asc')->pluck('id')->toArray()
        );
    }

    /**
     * @return void
     */
    public function test_write_helpers()
    {
        $tag1 = $this->createDirectory('a');
        $tag2 = $this->createDirectory('b');
        $tag3 = $this->createDirectory('c');

        $user = $this->createUser(null);

        // attach (scalar + array)
        $user->directories()->attach($tag1->id);
        $user->directories()->attach([$tag2->id]);
        $this->assertSame([$tag1->id, $tag2->id], $user->refresh()->tag_ids);

        // detach one
        $user->directories()->detach($tag1->id);
        $this->assertSame([$tag2->id], $user->refresh()->tag_ids);

        // sync
        $changes = $user->directories()->sync([$tag2->id, $tag3->id]);
        $this->assertSame([$tag3->id], $changes['attached']);
        $this->assertSame([], $changes['detached']);
        $this->assertSame([$tag2->id, $tag3->id], $user->refresh()->tag_ids);

        // toggle (retained ids come first, newly attached ids are appended)
        $user->directories()->toggle([$tag2->id, $tag1->id]);
        $this->assertSame([$tag3->id, $tag1->id], $user->refresh()->tag_ids);

        // detach all
        $user->directories()->detach();
        $this->assertNull($user->refresh()->tag_ids);
    }

    /**
     * Create a directory (the "related" model).
     *
     * @param string $title
     * @return \AnourValar\EloquentValidation\Tests\Models\Directory
     */
    private function createDirectory(string $title): Directory
    {
        $directory = new Directory();
        $directory->forceFill(['title' => $title])->save();

        return $directory;
    }

    /**
     * Create a user carrying the given list of directory ids.
     *
     * @param array<int, int>|null $tagIds
     * @return \AnourValar\EloquentValidation\Tests\Models\User
     */
    private function createUser(?array $tagIds): User
    {
        $user = new User();
        $user->forceFill(['name' => 'John Doe', 'email' => 'john@example.com', 'tag_ids' => $tagIds])->save();

        return $user;
    }
}
