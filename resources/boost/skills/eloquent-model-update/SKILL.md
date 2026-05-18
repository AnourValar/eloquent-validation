---
name: eloquent-validation
description: Use this skill when working with Eloquent models that include the \AnourValar\EloquentValidation\ModelTrait trait.
---

# Eloquent Validation: ModelTrait


## When to use this skill

Use this skill when working with Eloquent models that include the \AnourValar\EloquentValidation\ModelTrait trait.


## Features

- protected $trim -> trims attribute values on set.

- protected $nullable -> converts empty values to NULL on set.

- protected $computed -> attributes calculated inside the model or observer.

- protected $unchangeable -> attributes that cannot be changed for existing records.

- protected $unique -> list of sets of attributes that must be unique across records.


## REQUIRED when adding/changing model attributes

- Update localization attribute names in lang/*/models/<model>.php.

- Update OpenAPI schemas (light, heavy scopes) in resources/swagger/schemas/<model>_*.yaml [if exists].
