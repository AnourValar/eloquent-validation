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
     * @param string $key
     * @return void
     */
    protected function onChangedM2M(Model $model, string $column, string $relation, string $key = null): void
    {
        $sync = [];

        if ($model->exists) {
            foreach ((array) $model->$column as $value) {
                if (! is_null($key)) {
                    $sync[] = $value[$key];
                } else {
                    $sync[] = $value;
                }
            }
        }

        $model->$relation()->sync($sync);
    }
}
