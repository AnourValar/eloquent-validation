<?php

namespace AnourValar\EloquentValidation\Tests\Models;

/**
 * @property int $id
 */
class Directory extends \Illuminate\Database\Eloquent\Model
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
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
