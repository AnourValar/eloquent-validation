<?php

namespace AnourValar\EloquentValidation;

use \AnourValar\EloquentValidation\Exceptions\ValidationException;
use Illuminate\Support\Str;

trait ModelTrait
{
    /**
     * @var array
     */
    protected static $fillableDynamic = [];

    /**
     * Raw validation rules for all attributes
     *
     * @var mixed
     */
    private $rawRules = 'scalar';

    /**
     * Attribute names
     *
     * @var array
     */
    protected static $attributeNames;

    /**
     * "Save" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function saveValidation(\Illuminate\Validation\Validator $validator)
    {

    }

    /**
     * "Delete" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function deleteValidation(\Illuminate\Validation\Validator $validator)
    {

    }

    /**
     * @see \Illuminate\Database\Eloquent\Model
     *
     * @param string $key
     * @param string $value
     */
    public function setAttribute($key, $value)
    {
        if (isset($this->trim) && in_array($key, $this->trim) && is_scalar($value) && mb_strlen($value)) {
            $value = trim($value);
        }

        if (isset($this->nullable) && in_array($key, $this->nullable) && $value === '') {
            $value = null;
        }

        if (isset($value) && in_array($key, $this->getDates())) {
            if (is_scalar($value)) {
                if (!is_numeric($value)) {
                    $value = strtotime($value);
                }

                $value = date($this->getDateFormat(), $value);
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Save validation
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $prefix
     * @param array $customRules
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function scopeValidate(\Illuminate\Database\Eloquent\Builder $query, $prefix = null, array $customRules = [])
    {
        // Raw rules
        $validator = \Validator::make($this->attributes, array_fill_keys(array_keys($this->attributes), $this->rawRules));
        $validator->setAttributeNames($this->getAttributeNames());
        $passes = $validator->passes();

        $attributes = $this->getAttributesForValidation();

        // Handles
        if ($passes) {
            $validator = \Validator::make([], []);
            $validator->setAttributeNames($this->getAttributeNames());

            $validator->after(function ($validator)
            {
                if (!empty($this->calculated)) {
                    $this->handleUnchangeable($this->calculated, $validator, 'eloquent-validation::validation.calculated');
                }

                if (!empty($this->unchangeable) && $this->exists) {
                    $this->handleUnchangeable($this->unchangeable, $validator);
                }

                if (!empty($this->unique)) {
                    $this->handleUnique($this->unique, $validator);
                }
            });

            $passes = $validator->passes();
        }

        // Custom rules
        if ($passes && $customRules) {
            $validator = \Validator::make($attributes, $customRules);
            $validator->setAttributeNames($this->getAttributeNames());

            $passes = $validator->passes();
        }

        // Rules
        if ($passes) {
            $validator = \Validator::make($attributes, $this->canonizeRules());
            $validator->setAttributeNames($this->getAttributeNames());

            $passes = $validator->passes();
        }

        // After validation
        if ($passes) {
            $validator = \Validator::make($attributes, []);
            $validator->setAttributeNames($this->getAttributeNames());

            $validator->after([$this, 'saveValidation']);
            $passes = $validator->passes();

            if ($passes && $validator->getRules()) {
                $validator = \Validator::make($validator->getData(), $validator->getRules(), [], $validator->customAttributes);

                $passes = $validator->passes();
            }
        }

        if (! $passes) {
            throw new ValidationException($validator, null, 'default', $prefix);
        }

        return $this;
    }

    /**
     * Delete validation
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $prefix
     * @param array $customRules
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function scopeValidateDelete(\Illuminate\Database\Eloquent\Builder $query, $prefix = null, array $customRules = [])
    {
        $passes = true;
        $attributes = $this->getAttributesForValidation();

        // Custom rules
        if ($customRules) {
            $validator = \Validator::make($attributes, $customRules);
            $validator->setAttributeNames($this->getAttributeNames());

            $passes = $validator->passes();
        }

        // After validation
        if ($passes) {
            $validator = \Validator::make($attributes, []);
            $validator->setAttributeNames($this->getAttributeNames());

            $validator->after([$this, 'deleteValidation']);

            $passes = $validator->passes();
        }

        if (! $passes) {
            throw new ValidationException($validator, null, 'default', $prefix);
        }

        return $this;
    }

    /**
     * Temporary (for one query) list of "fillables" columns
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function scopeFields()
    {
        $args = func_get_args();
        array_shift($args);

        if (!isset($args[0])) {
            $args[0] = [];
        }

        if (is_array($args[0])) {
            $args = $args[0];
        }

        static::$fillableDynamic = &$args;

        return $this;
    }

    /**
     * @see \Illuminate\Database\Eloquent\Model::getFillable()
     *
     * @return array
     */
    public function getFillable()
    {
        if (!static::$fillableDynamic) {
            return parent::getFillable();
        }

        return static::$fillableDynamic;
    }

    /**
     * @see \Illuminate\Database\Eloquent\Model::save()
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        $list = [];
        static::$fillableDynamic = &$list;

        return parent::save($options);
    }

    /**
     * Set the attributes names
     *
     * @param array $attributeNames
     * @return void
     */
    public static function setAttributeNames(?array $attributeNames)
    {
        static::$attributeNames = &$attributeNames;
    }

    /**
     * Get the attributes names
     *
     * @return array
     */
    public function getAttributeNames()
    {
        if (is_null(static::$attributeNames)) {
            $attributeNames = [];

            if (\App::getLocale()) {
                $path = explode('\\', get_class($this));

                $file = Str::snake(array_pop($path));

                array_shift($path);
                $path = array_map([Str::class, 'snake'], $path);
                $dir = implode('/', $path);

                if (!$dir) {
                    $dir = 'models';
                }

                $attributeNames = trans($dir.'/'.$file.'.attributes');
                if (! is_array($attributeNames)) {
                    $attributeNames = [];
                }
            }

            static::$attributeNames = &$attributeNames;
        }

        return static::$attributeNames;
    }

    /**
     * Handle "unchangeable"
     *
     * @param array $unchangeable
     * @param \Illuminate\Validation\Validator $validator
     * @param string $translate
     */
    protected function handleUnchangeable(
        array $unchangeable,
        \Illuminate\Validation\Validator &$validator,
        $translate = 'eloquent-validation::validation.unchangeable'
    ) {
        if ($this->isUnguarded()) {
            return;
        }

        $newAttributes = $this->attributes;

        foreach ($unchangeable as $name) {
            if (array_key_exists($name, $newAttributes) && !$this->originalIsEquivalent($name, $newAttributes[$name])) {
                $validator->errors()->add(
                    $name,
                    trans($translate, ['attribute' => ($validator->customAttributes[$name] ?? $name)])
                );
            }
        }
    }

    /**
     * Handle "unique"
     *
     * @param array $uniques
     * @param \Illuminate\Validation\Validator $validator
     * @param string $translate
     */
    protected function handleUnique(
        array $uniques,
        \Illuminate\Validation\Validator &$validator,
        $translate = 'eloquent-validation::validation.unique'
    ) {
        $newAttributes = $this->attributes;
        $attributeNames = $this->getAttributeNames();

        foreach ($uniques as $unique) {
            $builder = new $this;

            foreach ($unique as $field) {
                if (isset($newAttributes[$field])) {
                    $builder = $builder->where($field, '=', $newAttributes[$field]);
                } else {
                    $builder = $builder->whereNull($field);
                }
            }

            if ($this->primaryKey && isset($newAttributes[$this->primaryKey])) {
                $builder = $builder->where($this->primaryKey, '!=', $newAttributes[$this->primaryKey]);
            }

            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($builder->getModel()))) {
                $builder = $builder->withTrashed();
            }

            if ($builder->first()) {
                $params = ['attributes' => []];
                foreach ($unique as $field) {
                    $params['attributes'][] = $attributeNames[$field] ?? $field;
                }
                $params['attributes'] = implode(', ', $params['attributes']);

                $validator->errors()->add($field, trans($translate, $params));
            }
        }
    }

    /**
     * @return array
     */
    protected function canonizeRules()
    {
        $rules = $this->rules ?? [];

        // "unique" validation
        $hasPrimary = ($this->primaryKey && isset($this->attributes[$this->primaryKey]));

        foreach ($rules as $field => &$fieldRules) {
            if (! is_array($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            foreach ($fieldRules as $key => $rule) {
                $rule = explode(':', $rule, 2);

                if (mb_strtolower($rule[0]) == 'unique') {
                    if (isset($rule[1])) {
                        $rule[1] = explode(',', $rule[1]);
                    } else {
                        $rule[1] = [];
                    }

                    if (count($rule[1]) == 0) {
                        $rule[1][] = $this->getConnectionName() ?
                            $this->getConnectionName().'.'.$this->getTable() :
                            $this->getTable();
                    }

                    if (count($rule[1]) == 1) {
                        $rule[1][] = $field;
                    }

                    if (count($rule[1]) == 2 && $hasPrimary) {
                        $rule[1][] = $this->attributes[$this->primaryKey];
                        $rule[1][] = $this->primaryKey;
                    }

                    $rule[1] = implode(',', $rule[1]);
                    $rule = implode(':', $rule);

                    $fieldRules[$key] = $rule;
                }
            }
        }
        unset($fieldRules);

        return $rules;
    }

    /**
     * @return array
     */
    protected function getAttributesForValidation()
    {
        $attributes = $this->attributes;

        foreach (array_keys($attributes) as $name) {
            if (isset($attributes[$name]) && $this->hasCast($name, ['json', 'array'])) {
                $attributes[$name] = $this->$name;
            }
        }

        return $attributes;
    }
}
