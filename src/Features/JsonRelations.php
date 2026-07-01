<?php

namespace AnourValar\EloquentValidation\Features;

use AnourValar\EloquentValidation\Features\Relations\BelongsToJson;

trait JsonRelations
{
    /**
     * Define a many-to-many relationship backed by a JSONB list of ids stored on this model,
     * instead of a pivot table.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $related
     * @param string $foreignKey JSONB column (list of ids) on this model
     * @param string|null $ownerKey Key on the related model (defaults to its primary key)
     * @return \AnourValar\EloquentValidation\Features\Relations\BelongsToJson<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function belongsToJson(string $related, string $foreignKey, ?string $ownerKey = null): BelongsToJson
    {
        $instance = $this->newRelatedInstance($related);

        return new BelongsToJson(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $ownerKey ?: $instance->getKeyName(),
        );
    }
}
