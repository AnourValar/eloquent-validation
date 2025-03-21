<?php

namespace AnourValar\EloquentValidation\Features;

use Illuminate\Database\Eloquent\Model;

trait ManyToManyTrait
{
    /**
     * On saved & deleted events
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $column
     * @param string $relation
     * @param string|null $key
     * @return void
     */
    protected function onChangedM2M(Model $model, string $column, string $relation, ?string $key = null): void
    {
        $sync = [];

        if ($model->exists) {
            foreach ((array) $model->$column as $name => $value) {
                if (! is_null($key)) {
                    $sync[] = $value[$key];
                } elseif (is_array($value)) {
                    $sync[] = $name;
                } else {
                    $sync[] = $value;
                }
            }
        }

        $model->$relation()->sync($sync);
    }
}
