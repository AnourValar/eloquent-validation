<?php

namespace AnourValar\EloquentValidation\Tests;

use AnourValar\EloquentValidation\CrudService;
use AnourValar\EloquentValidation\Tests\Models\Tag;

class CrudServiceTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_execute_create()
    {
        $counters = new CrudService()->execute(new Tag(), [['title' => 'Hello']]);

        $this->assertSame(['deleted' => 0, 'created' => 1, 'updated' => 0, 'affected' => 1], $counters);
        $this->assertDatabaseHas('tags', ['title' => 'Hello']);
    }

    /**
     * @return void
     */
    public function test_execute_update()
    {
        Tag::query()->insert(['title' => 'Old']);
        $id = Tag::query()->first()->id;

        $counters = new CrudService()->execute(new Tag(), [['id' => $id, 'title' => 'New']]);

        $this->assertSame(1, $counters['updated']);
        $this->assertSame(1, $counters['affected']);
        $this->assertDatabaseHas('tags', ['id' => $id, 'title' => 'New']);
    }

    /**
     * @return void
     */
    public function test_execute_update_no_changes()
    {
        Tag::query()->insert(['title' => 'Same']);
        $id = Tag::query()->first()->id;

        $counters = new CrudService()->execute(new Tag(), [['id' => $id, 'title' => 'Same']]);

        // nothing dirty => no update
        $this->assertSame(0, $counters['affected']);
    }

    /**
     * @return void
     */
    public function test_execute_delete()
    {
        Tag::query()->insert(['title' => 'ToDelete']);
        $id = Tag::query()->first()->id;

        $counters = new CrudService()->execute(new Tag(), [['id' => $id, '_delete' => true]]);

        $this->assertSame(1, $counters['deleted']);
        $this->assertSame(1, $counters['affected']);
        $this->assertDatabaseMissing('tags', ['id' => $id]);
    }

    /**
     * @return void
     */
    public function test_execute_create_trigger_fields()
    {
        // empty trigger field => row ignored
        $counters = new CrudService()->execute(new Tag(), [['title' => '']], 'title');

        $this->assertSame(0, $counters['affected']);
        $this->assertDatabaseCount('tags', 0);
    }

    /**
     * @return void
     */
    public function test_execute_skips_non_array_and_mutates()
    {
        $counters = new CrudService()->execute(
            new Tag(),
            ['not-an-array', ['title' => 'x']],
            null,
            function ($query) {
                $query['title'] = 'mutated';
                return $query;
            }
        );

        $this->assertSame(1, $counters['created']);
        $this->assertDatabaseHas('tags', ['title' => 'mutated']);
    }

    /**
     * @return void
     */
    public function test_execute_validation_failure()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        // title exceeds max:255 => validation fails for create
        new CrudService()->execute(new Tag(), [['title' => str_repeat('a', 300)]]);
    }

    /**
     * @return void
     */
    public function test_sync_insert_keep_delete()
    {
        Tag::query()->insert([['title' => 'a'], ['title' => 'b']]);

        new CrudService()->sync(Tag::query(), [['title' => 'b'], ['title' => 'c']]);

        $this->assertDatabaseMissing('tags', ['title' => 'a']); // removed
        $this->assertDatabaseHas('tags', ['title' => 'b']); // kept
        $this->assertDatabaseHas('tags', ['title' => 'c']); // inserted
        $this->assertDatabaseCount('tags', 2);
    }

    /**
     * @return void
     */
    public function test_sync_trigger_fields()
    {
        Tag::query()->insert(['title' => 'a']);

        // not triggered => no insert; existing row not present in request => deleted
        new CrudService()->sync(Tag::query(), [['title' => '']], 'title');

        $this->assertDatabaseCount('tags', 0);
    }

    /**
     * @return void
     */
    public function test_sync_unsupported_condition()
    {
        $this->expectException(\LogicException::class);

        $builder = Tag::query();
        $builder->whereIn('title', ['z']);

        new CrudService()->sync($builder, [['title' => 'new']]);
    }
}
