<?php

namespace AnourValar\EloquentValidation\Tests;

use AnourValar\EloquentValidation\Tests\Models\Account;
use AnourValar\EloquentValidation\Tests\Models\Post;
use AnourValar\EloquentValidation\Tests\Models\Tag;
use AnourValar\EloquentValidation\Tests\Models\User;

class ModelFeaturesTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_trim()
    {
        $account = new Account();

        $account->name = '  John  ';
        $this->assertSame('John', $account->name);

        // recursive trim for nested (json) attributes
        $account->tags = ['  a  ', ['  b  ', 'c']];
        $this->assertSame(['a', ['b', 'c']], $account->tags);
    }

    /**
     * @return void
     */
    public function test_nullable()
    {
        $account = new Account();

        $account->nickname = '';
        $this->assertNull($account->nickname);

        $account->nickname = '   ';
        $this->assertNull($account->nickname);

        $account->nickname = [];
        $this->assertNull($account->nickname);

        $account->nickname = 'Nick';
        $this->assertSame('Nick', $account->nickname);

        // not-nullable attribute keeps empty string
        $account->role = '';
        $this->assertSame('', $account->role);
    }

    /**
     * @return void
     */
    public function test_computed()
    {
        // not set => ok
        $account = new Account();
        $account->forceFill(['name' => 'John', 'email' => 'a@a']);
        $this->assertValidationSuccess($account);

        // set => rejected
        $account = new Account();
        $account->forceFill(['name' => 'John', 'email' => 'a@a', 'slug' => 'john']);
        $this->assertValidationFailed(
            $account,
            ['slug'],
            trans('eloquent-validation::validation.computed', ['attribute' => 'slug'])
        );
    }

    /**
     * @return void
     */
    public function test_unchangeable()
    {
        $account = new Account();
        $account->forceFill(['name' => 'John', 'email' => 'a@a']);
        $account->syncOriginal();
        $account->exists = true;

        // not changed => ok
        $this->assertValidationSuccess($account);

        // changed => rejected
        $account->forceFill(['email' => 'b@b']);
        $this->assertValidationFailed(
            $account,
            ['email'],
            trans('eloquent-validation::validation.unchangeable', ['attribute' => 'email'])
        );
    }

    /**
     * @return void
     */
    public function test_unchangeable_ignored_when_new()
    {
        // unchangeable only applies to existing models
        $account = new Account();
        $account->forceFill(['name' => 'John', 'email' => 'a@a']);
        $this->assertValidationSuccess($account);
    }

    /**
     * @return void
     */
    public function test_beforeValidate_success()
    {
        $account = new Account();
        $account->forceFill(['email' => 'a@a']);

        $this->assertSame($account, $account->beforeValidate(['email']));
    }

    /**
     * @return void
     */
    public function test_beforeValidate_validation_failure()
    {
        $account = new Account();
        $account->forceFill(['email' => 'a@a']);

        try {
            $account->beforeValidate(['name']);
            $this->assertTrue(false);
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Illuminate\Validation\ValidationException::class, $e);
            $this->assertArrayHasKey('name', $e->validator->errors()->toArray());
        }
    }

    /**
     * @return void
     */
    public function test_beforeValidate_unexpected_attribute()
    {
        $account = new Account();
        $account->forceFill(['email' => 'a@a']);

        $this->expectException(\LogicException::class);
        $account->beforeValidate(['nonexistent']);
    }

    /**
     * @return void
     */
    public function test_getters()
    {
        $account = new Account();
        $this->assertSame(['slug'], $account->getComputed());
        $this->assertSame(['email'], $account->getUnchangeable());
        $this->assertNull($account->getUnique());
        $this->assertNull($account->getJsonNested());

        $user = new User();
        $this->assertSame([['email'], ['name', 'role']], $user->getUnique());

        $post = new Post();
        $this->assertIsArray($post->getJsonNested());
        $this->assertArrayHasKey('data', $post->getJsonNested());
    }

    /**
     * @return void
     */
    public function test_extractAttributesListFromConfiguration()
    {
        $config = new Post()->extractAttributesListFromConfiguration();

        $this->assertSame(['data'], $config['nullable']);
        $this->assertNull($config['trim']);
        $this->assertNull($config['computed']);
        $this->assertNull($config['unchangeable']);
        $this->assertNull($config['unique']);
        $this->assertArrayHasKey('data', $config['jsonNested']);
        $this->assertSame([], $config['attributes']);
        $this->assertContains('data', $config['save_rules']);

        // nested rule keys (data.title) are excluded from save_rules
        $this->assertNotContains('data.title', $config['save_rules']);
    }

    /**
     * @return void
     */
    public function test_attribute_names()
    {
        Account::setAttributeNames(['name' => 'The Name', 'email' => 'The Email']);

        $names = new Account()->getAttributeNames();
        $this->assertSame('The Name', $names['name']);
        $this->assertSame('The Email', $names['email']);

        Account::setAttributeNames(null);
        $this->assertSame([], new Account()->getAttributeNames());
    }

    /**
     * @return void
     */
    public function test_scopeFields()
    {
        $tag = new Tag();
        $tag->fields(['title']);
        $this->assertSame(['title'], $tag->getFillable());

        $tag = new Tag();
        $tag->fields('title');
        $this->assertSame(['title'], $tag->getFillable());

        $tag = new Tag();
        $tag->fields();
        $this->assertSame([], $tag->getFillable());
    }

    /**
     * @return void
     */
    public function test_scopePublishFields()
    {
        $post = new Post();

        $post->publishFields('data');
        $this->assertSame(['data'], $post->getVisible());

        // publishFields resets the previous list
        $post->publishFields();
        $this->assertSame([], $post->getVisible());

        // addPublishFields appends
        $post->publishFields('data');
        $post->addPublishFields('data');
        $this->assertSame(['data', 'data'], $post->getVisible());
    }

    /**
     * @return void
     */
    public function test_authorize_allows()
    {
        \Gate::define('view-account', function ($user = null) {
            return true;
        });

        new Account()->authorize('view-account');
        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    public function test_authorize_denies()
    {
        \Gate::define('deny-account', function ($user = null) {
            return false;
        });

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        new Account()->authorize('deny-account');
    }

    /**
     * @return void
     */
    public function test_newInstance_copies_configuration()
    {
        $tag = new Tag();
        $tag->fields(['title']);
        $tag->setVisible(['title']);
        $tag->setHidden(['id']);

        $new = $tag->newInstance(['title' => 'Hello']);

        $this->assertSame(['title'], $new->getFillable());
        $this->assertSame(['title'], $new->getVisible());
        $this->assertSame(['id'], $new->getHidden());
        $this->assertSame('Hello', $new->title);
        $this->assertFalse($new->exists);
    }

    /**
     * @return void
     */
    public function test_withoutTrashedOr_requires_soft_deletes()
    {
        $this->expectException(\LogicException::class);
        Account::query()->withoutTrashedOr([1, 2]);
    }

    /**
     * @return void
     */
    public function test_mutateMerge()
    {
        $account = new Account();
        $account->tags = ['b' => 2];

        $method = new \ReflectionMethod($account, 'mutateMerge');
        $method->setAccessible(true);

        // array merge
        $result = $method->invoke($account, 'tags', ['a' => 1]);
        $this->assertArrayHasKey('tags', $result);
        $this->assertSame(['b' => 2, 'a' => 1], $account->tags);

        // null value => empty
        $this->assertSame([], $method->invoke($account, 'tags', null));

        // scalar value => json encoded
        $this->assertSame(['tags' => '5'], $method->invoke($account, 'tags', 5));
    }

    /**
     * @return void
     */
    public function test_mutateRecursively()
    {
        $account = new Account();

        $method = new \ReflectionMethod($account, 'mutateRecursively');
        $method->setAccessible(true);

        $trim = fn ($value) => is_string($value) ? trim($value) : $value;

        // without json encoding
        $this->assertSame(
            ['a' => 'x', 'b' => ['c' => 'y']],
            $method->invoke($account, ['a' => ' x ', 'b' => ['c' => ' y ']], $trim, false)
        );

        // with json encoding
        $this->assertSame('"x"', $method->invoke($account, ' x ', $trim, true));
    }
}
