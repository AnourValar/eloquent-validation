<?php

namespace AnourValar\EloquentValidation\Tests\Models;

/**
 * @property int $id
 * @property array<int, int>|null $tag_ids
 */
class User extends \Illuminate\Database\Eloquent\Model
{
    use \AnourValar\EloquentValidation\ModelTrait;
    use \AnourValar\EloquentValidation\Features\JsonRelations;

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
    protected $fillable = ['name', 'email', 'role', 'tag_ids'];

    /**
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'email' => 'string',
        'role' => 'string',
        'tag_ids' => 'array',
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
            'tag_ids' => ['nullable', 'array'],
        ];
    }

    /**
     * Many-to-many relation backed by the JSON "tag_ids" list (no pivot table).
     *
     * @return \AnourValar\EloquentValidation\Features\Relations\BelongsToJson
     */
    public function directories(): \AnourValar\EloquentValidation\Features\Relations\BelongsToJson
    {
        return $this->belongsToJson(Directory::class, 'tag_ids');
    }
}
