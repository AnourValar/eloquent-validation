<?php

namespace AnourValar\EloquentValidation\Tests\Models;

class Tag extends \Illuminate\Database\Eloquent\Model
{
    use \AnourValar\EloquentValidation\ModelTrait;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['title'];

    /**
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'title' => 'string',
    ];

    /**
     * @return array
     */
    public function saveRules()
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
