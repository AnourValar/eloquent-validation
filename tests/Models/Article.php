<?php

namespace AnourValar\EloquentValidation\Tests\Models;

class Article extends \Illuminate\Database\Eloquent\Model
{
    use \AnourValar\EloquentValidation\ModelTrait;
    use \AnourValar\EloquentValidation\Features\ManyToManyTrait;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['title', 'tag_ids'];

    /**
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'title' => 'string',
        'tag_ids' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'article_tag');
    }

    /**
     * Public proxy to the protected ManyToManyTrait method.
     *
     * @param string $column
     * @param string $relation
     * @param string|null $key
     * @return void
     */
    public function syncM2M(string $column, string $relation, ?string $key = null): void
    {
        $this->onChangedM2M($this, $column, $relation, $key);
    }
}
