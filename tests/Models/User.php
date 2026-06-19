<?php

namespace AnourValar\EloquentValidation\Tests\Models;

class User extends \Illuminate\Database\Eloquent\Model
{
    use \AnourValar\EloquentValidation\ModelTrait;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $unchangeable = ['email'];

    /**
     * @var array
     */
    protected $unique = [
        ['email'],
        ['name', 'role'],
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = ['name', 'email', 'role'];

    /**
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'email' => 'string',
        'role' => 'string',
    ];

    /**
     * @return array
     */
    public function saveRules()
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'string'],
            'role' => ['nullable', 'string'],
        ];
    }
}
