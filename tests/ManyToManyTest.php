<?php

namespace AnourValar\EloquentValidation\Tests;

use AnourValar\EloquentValidation\Tests\Models\Article;
use AnourValar\EloquentValidation\Tests\Models\Tag;

class ManyToManyTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_sync_list_of_ids()
    {
        Tag::query()->insert([['title' => 'a'], ['title' => 'b'], ['title' => 'c']]);

        $article = new Article();
        $article->forceFill(['title' => 'Post'])->save();

        $article->tag_ids = [1, 2];
        $article->syncM2M('tag_ids', 'tags');
        $article->load('tags');
        $this->assertSame([1, 2], $article->tags->pluck('id')->sort()->values()->all());

        // reducing the set
        $article->tag_ids = [2];
        $article->syncM2M('tag_ids', 'tags');
        $article->load('tags');
        $this->assertSame([2], $article->tags->pluck('id')->all());
    }

    /**
     * @return void
     */
    public function test_sync_by_key()
    {
        Tag::query()->insert([['title' => 'a'], ['title' => 'b'], ['title' => 'c']]);

        $article = new Article();
        $article->forceFill(['title' => 'Post'])->save();

        $article->tag_ids = [['id' => 1], ['id' => 3]];
        $article->syncM2M('tag_ids', 'tags', 'id');
        $article->load('tags');
        $this->assertSame([1, 3], $article->tags->pluck('id')->sort()->values()->all());
    }

    /**
     * @return void
     */
    public function test_sync_by_array_key()
    {
        Tag::query()->insert([['title' => 'a'], ['title' => 'b'], ['title' => 'c']]);

        $article = new Article();
        $article->forceFill(['title' => 'Post'])->save();

        // values are arrays, no key given => the element keys are used as ids
        $article->tag_ids = [1 => ['x' => 1], 3 => ['y' => 2]];
        $article->syncM2M('tag_ids', 'tags');
        $article->load('tags');
        $this->assertSame([1, 3], $article->tags->pluck('id')->sort()->values()->all());
    }

    /**
     * @return void
     */
    public function test_sync_detaches_all_when_empty()
    {
        Tag::query()->insert([['title' => 'a'], ['title' => 'b']]);

        $article = new Article();
        $article->forceFill(['title' => 'Post'])->save();
        $article->tags()->attach([1, 2]);

        $article->tag_ids = [];
        $article->syncM2M('tag_ids', 'tags');
        $article->load('tags');
        $this->assertCount(0, $article->tags);
    }
}
