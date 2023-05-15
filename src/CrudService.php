<?php

namespace AnourValar\EloquentValidation;

class CrudService
{
    /**
     * C,U,D operations
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param mixed $request
     * @param mixed $createTriggerFields
     * @param callable $mutator
     * @param mixed $validatePrefix
     * @throws \LogicException
     * @return array
     */
    public function execute(
        \Illuminate\Database\Eloquent\Model $model,
        $request,
        $createTriggerFields = null,
        callable $mutator = null,
        $validatePrefix = null
    ): array {
        $counters = ['deleted' => 0, 'created' => 0, 'updated' => 0];

        if (! $model->getKeyName()) {
            throw new \LogicException('The model must have a primary key.');
        }

        $request = $this->prepare($model, $request, $createTriggerFields);
        $fields = $model->getFillable();

        $policyExists = \Gate::getPolicyFor(get_class($model)) ? true : false;

        foreach ($request as $key => $query) {
            if ($mutator) {
                $query = $mutator($query);
            }
            if (! is_array($query)) {
                continue;
            }

            $model->fields($fields);
            $id = $query[$model->getKeyName()] ?? null;
            $currValidatePrefix = $this->validatePrefix($validatePrefix, $key);

            if (! $id) {
                // CREATE
                $model->fill($query)->validate($currValidatePrefix);

                if ($policyExists) {
                    \Gate::authorize('create', $model);
                }

                $counters['created'] += $model->create($query)->exists ? 1 : 0;
            }  elseif (! empty($query['_delete'])) {
                // DELETE
                $curr = null;
                if (is_numeric($id)) {
                    $curr = $model->find($id);
                }

                if ($curr) {
                    if ($policyExists) {
                        \Gate::authorize('delete', $curr);
                    }

                    $counters['deleted'] += $curr->validateDelete($currValidatePrefix)->delete() ? 1 : 0;
                }
            } else {
                // UPDATE
                $curr = null;
                if (is_numeric($id)) {
                    $curr = $model->find($id);
                }

                if ($curr) {
                    $curr->fill($query);

                    if ($curr->isDirty()) {
                        $curr->validate($currValidatePrefix);

                        if ($policyExists) {
                            \Gate::authorize('update', $curr);
                        }

                        $counters['updated'] += $curr->save() ? 1 : 0;
                    }
                }
            }
        }

        return $counters;
    }

    /**
     * Sync (Toggle)
     *
     * @param \Illuminate\Database\Eloquent\Builder $eloquent
     * @param mixed $request
     * @param mixed $triggerFields
     * @param mixed $validatePrefix
     * @throws \LogicException
     * @return void
     */
    public function sync(
        \Illuminate\Database\Eloquent\Builder $eloquent,
        $request,
        $triggerFields = null,
        $validatePrefix = null
    ): void {
        $collection = $eloquent->get();
        $triggerFields = (array) $triggerFields;
        $fields = $eloquent->getModel()->getFillable();

        foreach ((array) $request as $key => $query) {
            if (! is_array($query)) {
                continue;
            }
            if (! $this->isTriggered($query, $triggerFields)) {
                continue;
            }

            $item = $collection;
            foreach ($query as $name => $value) {
                $item = $item->where($name, '=', $value);
            }

            if ($item->first()) {
                // EXISTS
                $collection->forget($item->keys()->first());
            } else {
                // INSERT
                foreach ($eloquent->getQuery()->wheres as $where) {
                    if ($where['type'] == 'Basic') {
                        $query[$where['column']] = $where['value'];
                    } else {
                        throw new \LogicException('Unsupported condition in the QueryBuilder.');
                    }
                }

                $eloquent
                    ->newModelInstance()
                    ->fields($fields)
                    ->fill($query)
                    ->validate($this->validatePrefix($validatePrefix, $key))
                    ->save();
            }
        }

        foreach ($collection as $item) {
            // DELETE
            $item->validateDelete($validatePrefix)->delete();
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param mixed $request
     * @param mixed $createTriggerFields
     * @return array
     */
    private function prepare(\Illuminate\Database\Eloquent\Model $model, $request, $createTriggerFields)
    {
        $request = (array) $request;
        $createTriggerFields = (array) $createTriggerFields;

        foreach ($request as $key => $item) {
            if (! is_array($item)) {
                unset($request[$key]);
                continue;
            }

            if (empty($item[$model->getKeyName()]) && ! $this->isTriggered($item, $createTriggerFields)) {
                unset($request[$key]);
                continue;
            }

            unset($item[$model->getKeyName()]);
            if (! $item) {
                unset($request[$key]);
                continue;
            }
        }

        return $request;
    }

    /**
     * @param array $item
     * @param array $createTriggerFields
     * @return bool
     */
    private function isTriggered(array $item, array $createTriggerFields): bool
    {
        if (! $createTriggerFields) {
            return true;
        }

        foreach ($createTriggerFields as $field) {
            $curr = $item;
            foreach (explode('.', $field) as $part) {
                $curr = ($curr[$part] ?? null);
            }

            if (is_scalar($curr) && mb_strlen($curr)) {
                return true;
            }

            if (is_array($curr) && count($curr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $prefix
     * @param string $key
     * @return mixed
     */
    private function validatePrefix($prefix, string $key)
    {
        if (is_array($prefix)) {
            $prefix[] = $key;
        } else {
            if (mb_strlen((string) $prefix)) {
                $prefix .= '.';
            }

            $prefix .= $key;
        }

        return $prefix;
    }
}
