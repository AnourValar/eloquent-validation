<?php

namespace AnourValar\EloquentValidation\Tests\Models;

class Post extends \Illuminate\Database\Eloquent\Model
{
    use \AnourValar\EloquentValidation\ModelTrait;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected array $nullable = [
        'data',
    ];

    /**
     * Mutators for nested JSON.
     * jsonb - sort an array by key
     * nullable - '',[] => null convert (nested)
     * purges - remove null elements (nested)
     * types - set the type of value (nested)
     * sorts - sort an array (nested)
     * lists - drop array keys (nested)
     *
     * @var array
     */
    protected $jsonNested = [
        'data' => [
            'jsonb' => true,
            'nullable' => [],
            'purges' => [],
            'types' => [],
            'sorts' => [],
            'lists' => [],
        ],
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'json:unicode',
    ];

    /**
     * Get the validation rules
     *
     * @return array
     */
    public function saveRules()
    {
        return [
            'data' => ['nullable', 'array', 'array_keys'],
                'data.title' => ['required', 'string'],
        ];
    }
}
