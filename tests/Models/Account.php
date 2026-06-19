<?php

namespace AnourValar\EloquentValidation\Tests\Models;

class Account extends \Illuminate\Database\Eloquent\Model
{
    use \AnourValar\EloquentValidation\ModelTrait;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * Attributes that should be trimmed on set.
     *
     * @var array
     */
    protected array $trim = ['name', 'tags'];

    /**
     * Attributes that should be nullified ('', [] => null) on set.
     *
     * @var array
     */
    protected array $nullable = ['nickname'];

    /**
     * Attributes calculated automatically (cannot be set by hand).
     *
     * @var array
     */
    protected array $computed = ['slug'];

    /**
     * Attributes that cannot be changed once the model exists.
     *
     * @var array
     */
    protected array $unchangeable = ['email'];

    /**
     * @var array<int, string>
     */
    protected $fillable = ['name', 'nickname', 'slug', 'email', 'role', 'age', 'tags'];

    /**
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'nickname' => 'string',
        'slug' => 'string',
        'email' => 'string',
        'role' => 'string',
        'age' => 'integer',
        'tags' => 'json',
    ];

    /**
     * @return array
     */
    public function saveRules()
    {
        return [
            'name' => ['required', 'string'],
            'nickname' => ['nullable', 'string'],
            'slug' => ['nullable', 'string'],
            'email' => ['required', 'string'],
            'role' => ['nullable', 'string'],
            'age' => ['nullable', 'integer'],
            'tags' => ['nullable', 'array'],
        ];
    }
}
