<?php

namespace AnourValar\EloquentValidation\Features\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Many-to-many relation backed by a JSONB list of foreign keys stored on the parent,
 * instead of a pivot table (PostgreSQL).
 *
 * Storage: parent.{foreignKey} holds a JSONB array of related.{ownerKey} values, e.g. [1, 5, 9].
 *
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\Relation<TRelatedModel, TDeclaringModel, \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>>
 */
class BelongsToJson extends Relation
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query
     * @param TDeclaringModel $parent
     * @param string $foreignKey JSONB column (list of ids) on the parent
     * @param string $ownerKey Key on the related model (usually the primary key)
     */
    public function __construct(
        Builder $query,
        Model $parent,
        protected string $foreignKey,
        protected string $ownerKey,
    ) {
        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    #[\Override]
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->whereIn($this->getQualifiedOwnerKeyName(), $this->getForeignKeys($this->parent));
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array<int, TDeclaringModel> $models
     * @return void
     */
    #[\Override]
    public function addEagerConstraints(array $models): void
    {
        $keys = [];

        foreach ($models as $model) {
            foreach ($this->getForeignKeys($model) as $key) {
                $keys[$key] = $key;
            }
        }

        $this->query->whereIn($this->getQualifiedOwnerKeyName(), array_values($keys));
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array<int, TDeclaringModel> $models
     * @param string $relation
     * @return array<int, TDeclaringModel>
     */
    #[\Override]
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array<int, TDeclaringModel> $models
     * @param \Illuminate\Database\Eloquent\Collection<int, TRelatedModel> $results
     * @param string $relation
     * @return array<int, TDeclaringModel>
     */
    #[\Override]
    public function match(array $models, Collection $results, $relation): array
    {
        $dictionary = $results->keyBy($this->ownerKey);

        foreach ($models as $model) {
            $related = [];

            foreach ($this->getForeignKeys($model) as $key) {
                if ($dictionary->has($key)) {
                    $related[] = $dictionary->get($key);
                }
            }

            $model->setRelation($relation, $this->related->newCollection($related));
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TRelatedModel>
     */
    #[\Override]
    public function getResults()
    {
        return $this->getForeignKeys($this->parent) ? $this->get() : $this->related->newCollection();
    }

    /**
     * Add the constraints for an internal relationship existence query (JSON array containment).
     *
     * @param \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query
     * @param \Illuminate\Database\Eloquent\Builder<TDeclaringModel> $parentQuery
     * @param mixed $columns
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    #[\Override]
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $grammar = $query->getQuery()->getGrammar();

        $foreign = $grammar->wrap($this->parent->qualifyColumn($this->foreignKey));
        $owner = $grammar->wrap($query->getModel()->qualifyColumn($this->ownerKey));

        $query->select($columns);

        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $query->whereRaw("exists (select 1 from json_each({$foreign}) where value = {$owner})");
        } else {
            $query->whereRaw("{$foreign} @> to_jsonb({$owner})");
        }

        return $query;
    }

    /**
     * Attach the given ids to the parent's list (persists immediately, bypasses model validation).
     *
     * @param mixed $ids
     * @return void
     */
    public function attach($ids): void
    {
        $ids = $this->parseIds($ids);

        $this->setForeignKeys(array_merge($this->getForeignKeys($this->parent), $ids));
    }

    /**
     * Detach the given ids from the parent's list, or all of them when null (persists immediately).
     *
     * @param mixed $ids
     * @return void
     */
    public function detach($ids = null): void
    {
        if (is_null($ids)) {
            $this->setForeignKeys([]);

            return;
        }

        $ids = $this->parseIds($ids);

        $this->setForeignKeys(array_diff($this->getForeignKeys($this->parent), $ids));
    }

    /**
     * Set the parent's list to exactly the given ids (persists immediately, bypasses model validation).
     *
     * @param mixed $ids
     * @return array{attached: array<int, mixed>, detached: array<int, mixed>, updated: array<int, mixed>}
     */
    public function sync($ids): array
    {
        $ids = $this->parseIds($ids);
        $current = $this->getForeignKeys($this->parent);

        $changes = [
            'attached' => array_values(array_diff($ids, $current)),
            'detached' => array_values(array_diff($current, $ids)),
            'updated' => [],
        ];

        $this->setForeignKeys($ids);

        return $changes;
    }

    /**
     * Toggle the given ids on the parent's list (persists immediately).
     *
     * @param mixed $ids
     * @return array{attached: array<int, mixed>, detached: array<int, mixed>}
     */
    public function toggle($ids): array
    {
        $ids = $this->parseIds($ids);
        $current = $this->getForeignKeys($this->parent);

        $detach = array_values(array_intersect($current, $ids));
        $attach = array_values(array_diff($ids, $current));

        $this->setForeignKeys(array_merge(array_diff($current, $detach), $attach));

        return ['attached' => $attach, 'detached' => $detach];
    }

    /**
     * Get the fully qualified owner key name.
     *
     * @return string
     */
    public function getQualifiedOwnerKeyName(): string
    {
        return $this->related->qualifyColumn($this->ownerKey);
    }

    /**
     * Extract the JSONB list of foreign keys from a model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array<int, mixed>
     */
    protected function getForeignKeys(Model $model): array
    {
        $value = $model->{$this->foreignKey};

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return array_values(array_unique((array) $value));
    }

    /**
     * Persist a new list of foreign keys onto the parent.
     *
     * @param array<int, mixed> $ids
     * @return void
     */
    protected function setForeignKeys(array $ids): void
    {
        $ids = array_values(array_unique($ids));

        $this->parent->setAttribute($this->foreignKey, $ids ?: null);
        $this->parent->save();
    }

    /**
     * Normalize an ids argument (id, list of ids, model, or collection) into a flat array.
     *
     * @param mixed $ids
     * @return array<int, mixed>
     */
    protected function parseIds($ids): array
    {
        if ($ids instanceof Model) {
            return [$ids->getAttribute($this->ownerKey)];
        }

        if ($ids instanceof BaseCollection) {
            $ids = $ids->all();
        }

        return array_values(array_unique((array) $ids));
    }
}
